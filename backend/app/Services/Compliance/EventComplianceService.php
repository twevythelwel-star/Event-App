<?php

declare(strict_types=1);

namespace App\Services\Compliance;

use App\Models\Event;
use App\Models\EventComplianceAcknowledgement;
use App\Models\PolicyAcceptance;
use App\Models\User;
use App\Services\LegalComplianceService;
use Illuminate\Support\Facades\DB;

/**
 * The host's event-level compliance acknowledgement, separate from the policies accepted at registration.
 *
 * - Each event needs its own acknowledgement: the host confirms every food safety, alcohol and general legal
 *   declaration, after naming the event's applicable jurisdiction (state plus county or municipality).
 * - Each acknowledgement is stored as its own row in event_compliance_acknowledgements, with the jurisdiction and
 *   the food and alcohol arrangements it was given for. Nothing is ever recorded as accepted unless the host
 *   ticked it in that request.
 * - When the jurisdiction, food-service or alcohol-service arrangements materially change, the acknowledgement no
 *   longer covers the event: the change is refused unless the host acknowledges again in the same save, and an
 *   event cannot be published until its acknowledgement matches what it now does.
 */
final class EventComplianceService
{
    /** The versioned policies behind each group of declarations (PolicyCatalog). */
    public const POLICY_NAMES = [
        'food_safety' => 'event_food_safety_and_sanitation',
        'alcohol' => 'event_alcohol_service_and_age_verification',
        'general_legal' => 'event_general_legal_responsibility',
    ];

    public const CHANGE_LABELS = [
        'jurisdiction' => 'applicable jurisdiction',
        'food_service' => 'food-service arrangements',
        'alcohol_service' => 'alcohol-service arrangements',
    ];

    /** @return array<string, array{title: string, items: array<string, string>}> */
    public static function declarations(): array
    {
        return LegalComplianceService::eventComplianceDeclarations();
    }

    /** @return list<string> */
    public static function fieldNames(): array
    {
        return LegalComplianceService::eventComplianceDeclarationFieldNames();
    }

    /** Whether the request ticked any declaration (an attempt to acknowledge). */
    public function submitted(array $data): bool
    {
        foreach (self::fieldNames() as $field) {
            if (!empty($data[$field])) {
                return true;
            }
        }

        return false;
    }

    /**
     * The applicable jurisdiction the host gave: the event's state plus a county or municipality.
     *
     * @return array{country: string, state: string, county: string, municipality: string}
     */
    public function jurisdiction(array $data, ?Event $event = null): array
    {
        $pick = fn (string $key) => trim((string) (array_key_exists($key, $data) ? $data[$key] : $event?->{$key}));

        return [
            'country' => strtoupper($pick('country_code') ?: 'US'),
            'state' => strtoupper($pick('state_code')),
            'county' => $pick('compliance_jurisdiction_county'),
            'municipality' => $pick('compliance_jurisdiction_municipality'),
        ];
    }

    /** @throws \InvalidArgumentException when the jurisdiction is missing or any declaration is unticked */
    public function assertComplete(array $data, ?Event $event = null): void
    {
        $where = $this->jurisdiction($data, $event);
        if ($where['state'] === '' || ($where['county'] === '' && $where['municipality'] === '')) {
            throw new \InvalidArgumentException(
                "Give the event's applicable jurisdiction (its state, and its county or city) before completing the Event Compliance declarations."
            );
        }
        foreach (self::declarations() as $group) {
            $missing = array_filter(array_keys($group['items']), fn ($field) => empty($data[$field]));
            if ($missing !== []) {
                throw new \InvalidArgumentException(sprintf(
                    'Confirm every Event Compliance declaration. %d under "%s" %s not confirmed.',
                    count($missing), $group['title'], count($missing) === 1 ? 'is' : 'are'
                ));
            }
        }
    }

    public function activeAcknowledgement(Event $event): ?EventComplianceAcknowledgement
    {
        return EventComplianceAcknowledgement::where('event_id', (string) $event->id)
            ->whereNull('superseded_at_utc')
            ->latest('accepted_at_utc')
            ->first();
    }

    /**
     * Whether the event's acknowledgement covers what it does now.
     *
     * @return array{state: 'current'|'missing'|'outdated', acknowledgement: ?EventComplianceAcknowledgement}
     */
    public function status(Event $event): array
    {
        $ack = $this->activeAcknowledgement($event);
        if ($ack === null) {
            return ['state' => 'missing', 'acknowledgement' => null];
        }
        $current = LegalComplianceService::computeArrangementFingerprint(LegalComplianceService::extractComplianceState($event));
        if (!hash_equals($ack->arrangement_fingerprint, $current)) {
            return ['state' => 'outdated', 'acknowledgement' => $ack];
        }
        // A material change to the declarations themselves, in force after this acknowledgement, needs it again.
        $catalog = app(PolicyCatalog::class);
        foreach (self::POLICY_NAMES as $name) {
            $required = $catalog->required($name);
            $since = $required?->effective_at ?? $required?->published_at;
            if ($required && $required->source !== 'built_in' && $since && $ack->accepted_at_utc && $ack->accepted_at_utc->lt($since)) {
                return ['state' => 'outdated', 'acknowledgement' => $ack, 'policy_updated' => true];
            }
        }

        return ['state' => 'current', 'acknowledgement' => $ack];
    }

    /**
     * What a pending change does to the event's compliance arrangements.
     *
     * @return list<string> the changed categories (keys of CHANGE_LABELS)
     */
    public function materialChanges(Event $event, array $changes): array
    {
        $before = LegalComplianceService::extractComplianceState($event);
        $after = LegalComplianceService::extractComplianceState($event, $changes);

        return LegalComplianceService::detectMaterialComplianceChanges($before, $after)['categories'];
    }

    /**
     * Check a change before it is saved. Returns true when the request acknowledges the declarations (the caller
     * records them once the change is saved).
     *
     * @throws \InvalidArgumentException when an acknowledged event would materially change without a new acknowledgement
     */
    public function checkUpdate(Event $event, array $changes): bool
    {
        if ($this->submitted($changes)) {
            $this->assertComplete($changes, $event);

            return true;
        }
        $changed = $this->materialChanges($event, $changes);
        if ($changed !== [] && $this->activeAcknowledgement($event) !== null) {
            throw new \InvalidArgumentException(sprintf(
                "This change affects the event's %s. Review the Event Compliance declarations and confirm them again before saving.",
                self::describe($changed)
            ));
        }

        return false;
    }

    /**
     * Records a complete acknowledgement for the event, replacing any earlier one. Call it after the event's own
     * changes are saved: the acknowledgement covers the arrangements the event has at that moment.
     */
    public function record(User $user, Event $event, array $data, string $context): EventComplianceAcknowledgement
    {
        $this->assertComplete($data, $event);
        $where = $this->jurisdiction($data, $event);
        $event->forceFill([
            'compliance_jurisdiction_county' => $where['county'] ?: null,
            'compliance_jurisdiction_municipality' => $where['municipality'] ?: null,
        ]);
        $state = LegalComplianceService::extractComplianceState($event);
        $fingerprint = LegalComplianceService::computeArrangementFingerprint($state);
        $snapshot = fn (string $group) => collect(self::declarations()[$group]['items'])
            ->map(fn ($statement) => ['statement' => $statement, 'accepted' => true])->all();
        $request = request();
        $ip = $request?->ip();
        $agent = mb_substr((string) $request?->userAgent(), 0, 512) ?: null;
        $now = now()->utc();

        return DB::transaction(function () use ($user, $event, $context, $where, $state, $fingerprint, $snapshot, $ip, $agent, $now) {
            $previous = $this->activeAcknowledgement($event);
            if ($previous !== null) {
                $changed = LegalComplianceService::detectMaterialComplianceChanges(
                    // The acknowledgement row does not store these, so they are compared as unchanged.
                    $this->stateOf($previous) + collect($state)->only(['food_ordering_mode', 'food_fulfillment', 'food_arrangement', 'alcohol_arrangement'])->all(),
                    $state
                )['categories'];
                EventComplianceAcknowledgement::where('event_id', (string) $event->id)->whereNull('superseded_at_utc')->update([
                    'superseded_at_utc' => $now,
                    'superseded_reason' => $changed === []
                        ? 'Acknowledged again by the host.'
                        : 'Replaced after a change to the ' . self::describe($changed) . '.',
                ]);
            }

            $ack = EventComplianceAcknowledgement::create([
                'event_id' => (string) $event->id,
                'user_id' => $user->id,
                'policy_version' => LegalComplianceService::CURRENT_POLICY_VERSION,
                'acknowledgement_context' => $context,
                'jurisdiction_country' => $where['country'],
                'jurisdiction_state' => $where['state'],
                'jurisdiction_county' => $where['county'] ?: null,
                'jurisdiction_municipality' => $where['municipality'] ?: null,
                'supports_food_ordering' => (bool) $state['supports_food_ordering'],
                'food_permit_status' => $state['food_permit_status'] ?: null,
                'food_permit_number' => $state['food_permit_number'] ?: null,
                'serves_alcohol' => (bool) $state['serves_alcohol'],
                'alcohol_age_verification_required' => (bool) $state['alcohol_age_verification_required'],
                'alcohol_permit_status' => $state['alcohol_permit_status'] ?: null,
                'alcohol_permit_number' => $state['alcohol_permit_number'] ?: null,
                'food_safety_declarations' => $snapshot('food_safety'),
                'alcohol_declarations' => $snapshot('alcohol'),
                'general_legal_declarations' => $snapshot('general_legal'),
                'all_declarations_accepted' => true,
                'arrangement_fingerprint' => $fingerprint,
                'accepted_at_utc' => $now,
                'ip_address' => $ip,
                'user_agent' => $agent,
            ]);

            $event->forceFill([
                'compliance_acknowledgement_id' => $ack->id,
                'compliance_arrangement_fingerprint' => $fingerprint,
                'compliance_reacknowledgement_required' => false,
                'compliance_reacknowledgement_reason' => null,
            ])->save();

            // The same acceptance in the account's policy history, one row per group of declarations.
            foreach (self::POLICY_NAMES as $group => $policyName) {
                PolicyAcceptance::create([
                    'user_id' => $user->id,
                    'policy_name' => $policyName,
                    'policy_version' => app(PolicyCatalog::class)->current($policyName)?->version ?? LegalComplianceService::CURRENT_POLICY_VERSION,
                    'acceptance_status' => 'accepted',
                    'accepted_at_utc' => $now,
                    'ip_address' => $ip,
                    'user_agent' => $agent,
                    'context' => $context,
                    'context_entity_id' => (string) $event->id,
                    'metadata' => [
                        'event_title' => $event->title,
                        'event_compliance_acknowledgement_id' => $ack->id,
                        'declarations' => array_keys(self::declarations()[$group]['items']),
                        'jurisdiction' => $ack->jurisdictionLabel(),
                    ],
                ]);
            }

            return $ack;
        });
    }

    /** @param list<string> $categories */
    public static function describe(array $categories): string
    {
        $labels = array_values(array_map(fn ($c) => self::CHANGE_LABELS[$c] ?? $c, $categories));
        if (count($labels) <= 1) {
            return $labels[0] ?? 'arrangements';
        }

        return implode(', ', array_slice($labels, 0, -1)) . ' and ' . end($labels);
    }

    /** The arrangements an acknowledgement was given for, in extractComplianceState's shape. */
    private function stateOf(EventComplianceAcknowledgement $ack): array
    {
        return [
            'jurisdiction_country' => (string) $ack->jurisdiction_country,
            'jurisdiction_state' => (string) $ack->jurisdiction_state,
            'jurisdiction_county' => (string) $ack->jurisdiction_county,
            'jurisdiction_municipality' => (string) $ack->jurisdiction_municipality,
            'supports_food_ordering' => (bool) $ack->supports_food_ordering,
            'food_permit_status' => (string) $ack->food_permit_status,
            'food_permit_number' => (string) $ack->food_permit_number,
            'serves_alcohol' => (bool) $ack->serves_alcohol,
            'alcohol_age_verification_required' => (bool) $ack->alcohol_age_verification_required,
            'alcohol_permit_status' => (string) $ack->alcohol_permit_status,
            'alcohol_permit_number' => (string) $ack->alcohol_permit_number,
        ];
    }
}
