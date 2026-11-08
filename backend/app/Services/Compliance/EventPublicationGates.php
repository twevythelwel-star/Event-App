<?php

declare(strict_types=1);

namespace App\Services\Compliance;

use App\Enums\HostVerificationStatus;
use App\Enums\TenantRole;
use App\Models\Compliance\CompliancePublicationOverride;
use App\Models\Event;
use App\Models\HostApplication;
use App\Models\HostIdentityVerification;
use App\Models\OrganizerUser;
use App\Models\PolicyAcceptance;
use App\Models\User;
use App\Services\LegalComplianceService;

/**
 * The compliance gates an event must pass before it is published (EventManagementService::publicationReadiness
 * and every publishing path use them). An event cannot be published while:
 *
 *   1. identity     a host of the event's organizer has not passed government ID verification;
 *   2. host_policies a host has not accepted every mandatory host responsibility acknowledgement;
 *   3. event_ack    the event's own Event Compliance acknowledgement is missing or out of date;
 *   4. arrangements the food or alcohol declarations are incomplete or contradict each other;
 *   5–7. item:{key} a requirement an administrator's rule makes mandatory for this event's jurisdiction has no
 *                   permit submitted, or its verification is unresolved (submitted, pending, rejected, expired).
 *
 * Food requirements only apply when the event provides food, and alcohol requirements only when it provides
 * alcohol (the checklist leaves the others out). Only requirement items (5–7) can be overridden, and only by a
 * compliance administrator with a documented reason and an expiry date (CompliancePublicationOverride). Gates
 * 1–4 are the host's own identity and legal statements: no administrator can give them on the host's behalf.
 */
final class EventPublicationGates
{
    public const OVERRIDABLE_PREFIX = 'item:';

    public function __construct(
        private readonly EventComplianceService $acknowledgements,
        private readonly EventRequirementChecklist $checklist,
    ) {
    }

    /**
     * @return list<array{code: string, section: string, message: string, overridable: bool, override: ?CompliancePublicationOverride}>
     */
    public function check(Event $event, ?User $actor = null): array
    {
        $gates = [];
        $add = function (string $code, string $section, string $message, bool $overridable = false) use (&$gates) {
            $gates[] = ['code' => $code, 'section' => $section, 'message' => $message, 'overridable' => $overridable, 'override' => null];
        };

        // 0: a compliance administrator suspended this event, or a host's publishing privileges. Never overridable.
        if ($event->compliance_suspended_at !== null) {
            $add('event_suspended', 'identity', 'EventBite suspended this event on compliance grounds'
                . ($event->compliance_suspension_reason ? ': ' . $event->compliance_suspension_reason : '') . '. It cannot be published until the suspension is lifted.');
        }

        // 1 and 2: the hosts behind the event (its organizer's event owners, and a host publishing it themselves).
        $hosts = $this->hosts($event, $actor);
        if ($hosts === []) {
            $add('identity', 'identity', "This event's organizer has no active event host whose identity can be verified.");
        }
        foreach ($hosts as $host) {
            if ($suspension = \App\Models\Compliance\HostPublishingSuspension::activeFor((int) $host->id)) {
                $add('host_suspended', 'identity', "{$host->name}'s publishing privileges are suspended: {$suspension->reason}");
            }
            if (!self::identityVerified($host)) {
                $add('identity', 'identity', "{$host->name} has not passed government ID verification. An approved, unexpired ID verification is needed before this event can be published.");
            }
            $missing = self::missingHostPolicies($host);
            if ($missing !== []) {
                $add('host_policies', 'identity', "{$host->name} has not accepted these mandatory host acknowledgements: " . implode('; ', $missing) . '.');
            }
        }

        // 3: this event's own acknowledgement, covering what it does now.
        $ackStatus = $this->acknowledgements->status($event);
        $status = $ackStatus['state'];
        if ($status !== 'current') {
            $add('event_ack', 'event-compliance', match (true) {
                $status === 'missing' => "Give the event's applicable jurisdiction and confirm the Event Compliance declarations for this event.",
                !empty($ackStatus['policy_updated']) => 'EventBite updated the Event Compliance declarations since they were confirmed for this event. Review and confirm them again.',
                default => "The event's jurisdiction, food-service or alcohol-service arrangements changed after the Event Compliance declarations were confirmed. Confirm them again.",
            });
        }

        // 4: the food and alcohol declarations.
        foreach ($this->arrangementProblems($event) as $problem) {
            $add('arrangements', 'event-compliance', $problem);
        }

        // 5–7: requirements an administrator's rule makes mandatory here, for what this event provides.
        $built = $this->checklist->build(EventRequirementChecklist::factsFromEvent($event), $event);
        foreach ($built['sections'] as $section) {
            foreach ($section['items'] as $item) {
                if ($item['base'] !== 'required') {
                    continue;
                }
                $where = "{$item['title']} ({$item['level']['name']}, {$section['label']})";
                $message = match ($item['status']) {
                    'required' => "Submit the permit or licence for {$where}.",
                    'submitted', 'pending_verification' => "{$where} is waiting for an EventBite administrator to verify it.",
                    'rejected' => "{$where} was rejected" . ($item['evidence']?->review_comment ? ": {$item['evidence']->review_comment}" : '') . '. Submit it again.',
                    'expired' => "{$where} has expired. Submit the renewed permit or licence.",
                    default => null,
                };
                if ($message !== null) {
                    $add(self::OVERRIDABLE_PREFIX . $item['key'], 'requirements', $message, true);
                }
            }
        }

        return $this->applyOverrides($event, $gates);
    }

    /** The gates still blocking publication (not overridden). */
    public function blocking(Event $event, ?User $actor = null): array
    {
        return array_values(array_filter($this->check($event, $actor), fn ($gate) => $gate['override'] === null));
    }

    /** @return list<User> the event's organizer's active event owners, plus a host publishing it themselves */
    public function hosts(Event $event, ?User $actor = null): array
    {
        $owners = User::whereIn('id', OrganizerUser::where('organizer_id', $event->organizer_id)
            ->where('role', TenantRole::EVENT_OWNER->value)->where('is_active', true)->pluck('user_id'))->get();
        if ($actor && !$actor->hasRole(\App\Enums\AccountType::SYSTEM_ADMIN) && !$owners->contains('id', $actor->id)) {
            $owners->push($actor);
        }

        return $owners->filter(fn (User $user) => !$user->hasRole(\App\Enums\AccountType::SYSTEM_ADMIN) || $user->hasRole(\App\Enums\AccountType::EVENT_HOST))->values()->all();
    }

    /** An approved, unexpired government ID verification, or a host application whose ID check was approved. */
    public static function identityVerified(User $user): bool
    {
        $verification = HostIdentityVerification::where('user_id', $user->id)->latest()->first();
        if ($verification) {
            return $verification->status === HostVerificationStatus::APPROVED->value && !$verification->isDocumentExpired();
        }
        $application = HostApplication::where('user_id', $user->id)->latest()->first();

        return $application !== null && in_array((string) $application->identity_verification_status, ['verified', 'approved'], true);
    }

    /** @return list<string> titles of the current host responsibility acknowledgements this user has not accepted */
    public static function missingHostPolicies(User $user): array
    {
        // Every mandatory host acknowledgement in force for this host (by jurisdiction), at its required version:
        // a material change published since needs accepting again; a minor one does not.
        $catalog = app(PolicyCatalog::class);
        $missing = [];
        foreach ($catalog->group('host') as $name => $spec) {
            if ($spec['mandatory'] && $catalog->appliesTo($user, $spec, ignoreRole: true) && $catalog->state($user, $name) !== 'accepted') {
                $missing[] = $spec['title'];
            }
        }

        return $missing;
    }

    /** @return list<string> */
    private function arrangementProblems(Event $event): array
    {
        $problems = [];
        if ($event->food_service_mode === null) {
            $problems[] = 'Say whether this event provides food, and whether it is sold or complimentary (Event Compliance: "Food at this event").';
        }
        if ($event->alcohol_service_mode === null) {
            $problems[] = 'Say whether this event provides alcohol, and how: sold, included in the ticket, complimentary or BYOB (Event Compliance: "Alcohol at this event").';
        }
        $food = $event->food_service_mode;
        $alcohol = $event->alcohol_service_mode;
        if ($food === 'none' && $event->supports_food_ordering) {
            $problems[] = 'Food ordering is on, but "Food at this event" says no food. Correct one of them.';
        }
        if ($alcohol === 'none' && $event->serves_alcohol) {
            $problems[] = 'Alcohol service is ticked, but "Alcohol at this event" says no alcohol. Correct one of them.';
        }
        if ($food !== null && $food !== 'none') {
            if (trim((string) $event->food_permit_status) === '') {
                $problems[] = 'Give the food-service permit and inspection status.';
            }
            if (!$event->allergen_disclosure_confirmed || !$event->temperature_and_sanitation_confirmed) {
                $problems[] = 'Confirm the food temperature and sanitation, and the allergen disclosures.';
            }
        }
        if ($alcohol !== null && $alcohol !== 'none') {
            if (!$event->alcohol_age_verification_required) {
                $problems[] = 'Alcohol is provided: confirm the legal drinking age and government ID checks.';
            }
            // Selling, including or giving alcohol needs a licence status unless a licensed vendor serves it; BYOB does not sell or serve.
            if ($alcohol !== 'byob' && !$event->uses_licensed_alcohol_vendor
                && (trim((string) $event->alcohol_permit_status) === '' || $event->alcohol_permit_status === 'no_alcohol')) {
                $problems[] = 'Give the alcohol licence or special-event permit status, or say a licensed alcohol vendor serves it.';
            }
        }

        return $problems;
    }

    private function applyOverrides(Event $event, array $gates): array
    {
        $overrides = CompliancePublicationOverride::with('grantor')->where('event_id', $event->id)->active()->get()->keyBy('gate_code');
        foreach ($gates as &$gate) {
            if ($gate['overridable'] && $overrides->has($gate['code'])) {
                $gate['override'] = $overrides->get($gate['code']);
            }
        }

        return $gates;
    }
}
