<?php

declare(strict_types=1);

namespace App\Services\Compliance;

use App\Models\Compliance\CompliancePolicyVersion;
use App\Models\User;
use Illuminate\Support\Facades\Schema;

/**
 * The current version of every policy and host acknowledgement.
 *
 * The built-in policies (LegalComplianceService) are the starting point. When an administrator publishes a version
 * (CompliancePolicyVersion), it replaces that policy's version, title and text; a published policy that is not
 * built in is a new acknowledgement. Everyone it applies to must then accept the current version: hosts see it on
 * their Compliance and verification page, and an unaccepted host acknowledgement blocks publishing
 * (EventPublicationGates::missingHostPolicies).
 *
 * Bound as a scoped singleton, so the published versions are read once per request.
 */
final class PolicyCatalog
{
    /** @var array<string, CompliancePolicyVersion>|null */
    private ?array $published = null;

    /** @return array<string, CompliancePolicyVersion> current published version per policy name */
    public function published(): array
    {
        if ($this->published === null) {
            $this->published = [];
            try {
                if (Schema::hasTable('compliance_policy_versions')) {
                    foreach (CompliancePolicyVersion::where('status', 'published')->orderBy('published_at')->orderBy('id')->get() as $version) {
                        $this->published[$version->policy_name] = $version;
                    }
                }
            } catch (\Throwable) {
                // No database (standalone unit runner): the built-in versions apply.
            }
        }

        return $this->published;
    }

    /** Apply published versions to built-in specs (same keys and form fields; only version, title and text change). */
    public function overlay(array $specs): array
    {
        $published = $this->published();
        foreach ($specs as $key => $spec) {
            $version = $published[$spec['policy_name']] ?? null;
            if ($version) {
                $specs[$key] = self::withVersion($spec, $version);
            }
        }

        return $specs;
    }

    /**
     * Every policy in a group (account or host), built-in and newly published, at its current version.
     *
     * @return array<string, array{policy_name: string, title: string, version: string, description: string, group: string, built_in: bool}>
     */
    public function group(string $group): array
    {
        $builtIn = $group === 'account'
            ? \App\Services\LegalComplianceService::registrationPolicies()
            : \App\Services\LegalComplianceService::hostResponsibilityPolicies();
        $specs = [];
        foreach ($builtIn as $spec) {
            $specs[$spec['policy_name']] = $spec + [
                'description' => $spec['description'] ?? implode(' ', $spec['clauses'] ?? []),
                'group' => $group,
                'built_in' => true,
            ];
        }
        foreach ($this->published() as $name => $version) {
            if ($version->policy_group === $group && !isset($specs[$name])) {
                $specs[$name] = self::withVersion(['policy_name' => $name, 'group' => $group, 'built_in' => false], $version);
            }
        }

        return $specs;
    }

    /** @return array<string, array> every account policy and host acknowledgement */
    public function all(): array
    {
        return $this->group('account') + $this->group('host');
    }

    /** Active event hosts, who must accept a newly published host acknowledgement before they can publish again. */
    public static function affectedCount(string $group): int
    {
        return $group === 'host'
            ? User::whereIn('id', fn ($q) => $q->select('user_id')->from('user_roles')->where('role', 'event_host')->where('is_active', true))->count()
            : User::count();
    }

    private static function withVersion(array $spec, CompliancePolicyVersion $version): array
    {
        $lines = array_values(array_filter(array_map('trim', preg_split('/\R/', (string) $version->body) ?: [])));

        return array_merge($spec, [
            'version' => $version->version,
            'title' => $version->title,
            'description' => trim(implode(' ', $lines)),
            'clauses' => $lines,
        ]);
    }
}
