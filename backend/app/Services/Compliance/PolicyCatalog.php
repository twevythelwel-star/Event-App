<?php

declare(strict_types=1);

namespace App\Services\Compliance;

use App\Enums\AccountType;
use App\Models\Compliance\CompliancePolicyVersion;
use App\Models\Event;
use App\Models\OrganizerUser;
use App\Models\PolicyAcceptance;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Schema;

/**
 * The version-controlled policy catalog.
 *
 * Every policy is a series of CompliancePolicyVersion rows, never overwritten. For each policy:
 *   - the current version is the latest published version whose effective date has passed;
 *   - the required version is the latest of those that requires re-acknowledgement (a material change), or the
 *     first version. A person is up to date when they accepted the required version or a later one, so a
 *     non-material update (a typo fix) does not ask everyone to accept again.
 *
 * A policy applies to a person by role (every user, attendees, event hosts, administrators) and, when it lists
 * jurisdictions, only where they host events or hold orders. Mandatory policies they are not up to date with are
 * "outstanding": updates are asked for at their next interaction (RequirePolicyAcknowledgement), and outstanding host
 * acknowledgements block event publishing (EventPublicationGates).
 *
 * Bound as a scoped singleton, so versions are read once per request.
 */
final class PolicyCatalog
{
    /** Event declarations are acknowledged per event (EventComplianceService), not per person. */
    public const PER_EVENT_CATEGORY = 'event_compliance';

    /** @var array<string, list<CompliancePolicyVersion>>|null in-force versions per policy, oldest first */
    private ?array $inForce = null;

    /** @var array<int, array<string, list<string>>> accepted versions per user and policy */
    private array $accepted = [];

    /** @var array<int, list<string>> */
    private array $jurisdictions = [];

    /** @return array<string, list<CompliancePolicyVersion>> */
    public function versions(): array
    {
        if ($this->inForce === null) {
            $this->inForce = [];
            try {
                if (Schema::hasTable('compliance_policy_versions')) {
                    $rows = CompliancePolicyVersion::whereIn('status', ['published', 'retired'])
                        ->where(fn ($q) => $q->whereNull('effective_at')->orWhere('effective_at', '<=', now()))
                        ->get()->sortBy(fn ($v) => [($v->effective_at ?? $v->published_at)?->timestamp ?? 0, $v->id]);
                    foreach ($rows as $row) {
                        $this->inForce[$row->policy_name][] = $row;
                    }
                }
            } catch (\Throwable) {
                // No database (standalone unit runner): the built-in text applies.
            }
        }

        return $this->inForce;
    }

    /** @return array<string, CompliancePolicyVersion> current version per policy */
    public function published(): array
    {
        return array_map(fn (array $list) => end($list), $this->versions());
    }

    public function current(string $name): ?CompliancePolicyVersion
    {
        return $this->published()[$name] ?? null;
    }

    public function required(string $name): ?CompliancePolicyVersion
    {
        $list = $this->versions()[$name] ?? [];
        $required = $list[0] ?? null;
        foreach ($list as $version) {
            if ($version->requires_reacknowledgement) {
                $required = $version;
            }
        }

        return $required;
    }

    /** Published versions not yet in force (effective later). */
    public function upcoming(): Collection
    {
        try {
            return CompliancePolicyVersion::where('status', 'published')->where('effective_at', '>', now())->orderBy('effective_at')->get();
        } catch (\Throwable) {
            return collect();
        }
    }

    /** Apply current versions to built-in specs (same keys and form fields; only version, title and text change). */
    public function overlay(array $specs): array
    {
        foreach ($specs as $key => $spec) {
            if ($version = $this->current($spec['policy_name'])) {
                $specs[$key] = self::withVersion($spec, $version);
            }
        }

        return $specs;
    }

    /**
     * Every per-person policy for a group: "account" (applies to every user or attendees) or "host" (event hosts).
     *
     * @return array<string, array>
     */
    public function group(string $group): array
    {
        $builtIn = $group === 'account'
            ? \App\Services\LegalComplianceService::registrationPolicies()
            : \App\Services\LegalComplianceService::hostResponsibilityPolicies();
        $specs = [];
        foreach ($builtIn as $spec) {
            $specs[$spec['policy_name']] = $spec + ['description' => $spec['description'] ?? implode(' ', $spec['clauses'] ?? [])];
        }
        foreach ($this->published() as $name => $version) {
            if ($version->category === self::PER_EVENT_CATEGORY || isset($specs[$name])) {
                continue;
            }
            $roles = $version->roles();
            $isHost = in_array('event_host', $roles, true) && !in_array('all', $roles, true);
            if (($group === 'host') === $isHost) {
                $specs[$name] = self::withVersion(['policy_name' => $name], $version);
            }
        }
        foreach ($specs as $name => &$spec) {
            $spec = $this->describe($name, $spec, $group);
        }

        return $specs;
    }

    /** @return array<string, array> every per-person policy */
    public function all(): array
    {
        return $this->group('account') + $this->group('host');
    }

    /** Mandatory per-person policies that apply to this user (by role and jurisdiction). */
    public function applicable(User $user): array
    {
        return array_filter($this->all(), fn ($spec) => $spec['mandatory'] && $this->appliesTo($user, $spec));
    }

    /** "accepted" (up to date), "updated" (accepted an earlier version only) or "not_accepted". */
    public function state(User $user, string $name): string
    {
        $accepted = $this->acceptedVersions($user)[$name] ?? [];
        if ($accepted === []) {
            return 'not_accepted';
        }
        $list = $this->versions()[$name] ?? [];
        if ($list === []) {
            return 'accepted'; // no stored versions: any acceptance counts (isolated runner)
        }
        $positions = array_flip(array_map(fn ($v) => $v->version, $list));
        $required = $this->required($name);
        $requiredAt = $positions[$required->version] ?? 0;
        foreach ($accepted as $version) {
            if (isset($positions[$version]) && $positions[$version] >= $requiredAt) {
                return 'accepted';
            }
        }

        return 'updated';
    }

    /**
     * Mandatory policies this user must still accept. With $updatesOnly, only versions an administrator published
     * (new policies and material changes); the original versions are accepted at registration and host application.
     *
     * @return array<string, array>
     */
    public function outstanding(User $user, bool $updatesOnly = false): array
    {
        if ($updatesOnly && !$this->hasAdministratorVersions()) {
            return [];
        }
        $out = [];
        foreach ($this->applicable($user) as $name => $spec) {
            if ($updatesOnly && ($this->required($name)?->source ?? 'built_in') === 'built_in') {
                continue;
            }
            $state = $this->state($user, $name);
            if ($state !== 'accepted') {
                $out[$name] = $spec + ['state' => $state];
            }
        }

        return $out;
    }

    /**
     * Record acceptance of the current version of each named policy: one append-only record per policy, linked to
     * the exact version. Earlier records are kept.
     *
     * @return int records created
     */
    public function record(User $user, array $names, string $context, ?Request $request = null): int
    {
        return \Illuminate\Support\Facades\DB::transaction(fn () => $this->recordEach($user, $names, $context, $request));
    }

    private function recordEach(User $user, array $names, string $context, ?Request $request): int
    {
        $created = 0;
        foreach (array_unique($names) as $name) {
            $spec = $this->all()[$name] ?? null;
            if (!$spec || in_array($spec['version'], $this->acceptedVersions($user)[$name] ?? [], true)) {
                continue;
            }
            PolicyAcceptance::create([
                'user_id' => $user->id,
                'policy_name' => $name,
                'policy_version' => $spec['version'],
                'policy_version_id' => $spec['version_id'],
                'acceptance_status' => 'accepted',
                'accepted_at_utc' => now('UTC'),
                'ip_address' => $request?->ip(),
                'user_agent' => $request ? substr((string) $request->userAgent(), 0, 512) : null,
                'context' => $context,
                'context_entity_id' => (string) $user->id,
                'metadata' => ['title' => $spec['title'], 'previous_versions' => $this->acceptedVersions($user)[$name] ?? []],
            ]);
            $this->accepted[$user->id][$name][] = $spec['version'];
            $created++;
        }

        return $created;
    }

    /**
     * People a version applies to who are not up to date with it: who must acknowledge it.
     *
     * @return \Illuminate\Support\LazyCollection<int, User>
     */
    public function affectedUsers(CompliancePolicyVersion $version)
    {
        $roles = $version->roles();
        $query = User::query()->where('status', 'active');
        if (!in_array('all', $roles, true) && !in_array('attendee', $roles, true)) {
            $dbRoles = [];
            foreach ($roles as $role) {
                array_push($dbRoles, ...($role === 'admin' ? ['admin', 'system_admin'] : [$role]));
            }
            $query->whereIn('id', fn ($q) => $q->select('user_id')->from('user_roles')->where('is_active', true)->whereIn('role', $dbRoles));
        }
        $spec = $this->describe($version->policy_name, self::withVersion(['policy_name' => $version->policy_name], $version), in_array('event_host', $roles, true) ? 'host' : 'account');

        return $query->orderBy('id')->lazy()->filter(fn (User $user) => $this->appliesTo($user, $spec)
            && ($this->current($version->policy_name)?->id !== $version->id || $this->state($user, $version->policy_name) !== 'accepted'));
    }

    public static function affectedCount(string $group): int
    {
        return $group === 'host'
            ? User::whereIn('id', fn ($q) => $q->select('user_id')->from('user_roles')->where('role', 'event_host')->where('is_active', true))->count()
            : User::count();
    }

    // ----------------------------------------------------------------------------------------------------------

    private function hasAdministratorVersions(): bool
    {
        foreach ($this->published() as $name => $version) {
            if (($this->required($name)?->source ?? 'built_in') !== 'built_in' && $version->acknowledgement_mandatory) {
                return true;
            }
        }

        return false;
    }

    public function appliesTo(User $user, array $spec, bool $ignoreRole = false): bool
    {
        $roles = $spec['roles'];
        $roleMatch = $ignoreRole || in_array('all', $roles, true)
            || (in_array('attendee', $roles, true))
            || (in_array('event_host', $roles, true) && $user->hasRole(AccountType::EVENT_HOST))
            || (in_array('admin', $roles, true) && ($user->hasRole(AccountType::ADMINISTRATOR) || $user->hasRole(AccountType::SYSTEM_ADMIN)));
        if (!$roleMatch) {
            return false;
        }
        if ($spec['jurisdictions'] === []) {
            return true;
        }

        return array_intersect($spec['jurisdictions'], $this->userJurisdictions($user)) !== [];
    }

    /** States where the user hosts current events or holds orders. */
    private function userJurisdictions(User $user): array
    {
        if (!isset($this->jurisdictions[$user->id])) {
            $organizers = OrganizerUser::where('user_id', $user->id)->where('is_active', true)->pluck('organizer_id');
            $hosted = Event::allTenants()->whereIn('organizer_id', $organizers)->where('status', '!=', 'cancelled')->pluck('state_code');
            $attended = Event::allTenants()->whereIn('id', fn ($q) => $q->select('event_id')->from('orders')->where('user_id', $user->id))->pluck('state_code');
            $this->jurisdictions[$user->id] = $hosted->merge($attended)->filter()->map(fn ($s) => strtoupper((string) $s))->unique()->values()->all();
        }

        return $this->jurisdictions[$user->id];
    }

    /** @return array<string, list<string>> */
    private function acceptedVersions(User $user): array
    {
        if (!isset($this->accepted[$user->id])) {
            $this->accepted[$user->id] = PolicyAcceptance::where('user_id', $user->id)->where('acceptance_status', 'accepted')
                ->orderByDesc('accepted_at_utc')->get(['policy_name', 'policy_version'])
                ->groupBy('policy_name')->map(fn ($rows) => $rows->pluck('policy_version')->unique()->values()->all())->all();
        }

        return $this->accepted[$user->id];
    }

    private function describe(string $name, array $spec, string $group): array
    {
        $current = $this->current($name);
        $required = $this->required($name);

        return $spec + [
            'policy_name' => $name,
            'group' => $group,
            'built_in' => ($this->versions()[$name][0]->source ?? 'built_in') === 'built_in',
            'version_id' => $current?->id,
            'category' => $current?->category ?? ($group === 'host' ? 'host_responsibility' : 'other'),
            'roles' => $current ? $current->roles() : ($group === 'host' ? ['event_host'] : ['all']),
            'jurisdictions' => $current ? $current->jurisdictions() : [],
            'mandatory' => $current ? (bool) $current->acknowledgement_mandatory : true,
            'requires_reacknowledgement' => $current ? (bool) $current->requires_reacknowledgement : true,
            'required_version' => $required?->version ?? ($spec['version'] ?? null),
            'effective_at' => $current?->effective_at,
            'change_summary' => $current?->change_summary,
        ];
    }

    private static function withVersion(array $spec, CompliancePolicyVersion $version): array
    {
        $lines = array_values(array_filter(array_map('trim', preg_split('/\R/', (string) $version->body) ?: [])));

        return array_merge($spec, [
            'version' => $version->version,
            'title' => $version->title,
            'description' => trim(implode(' ', $lines)),
            'clauses' => $lines,
            'body' => (string) $version->body,
        ]);
    }
}
