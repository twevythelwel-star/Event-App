<?php

declare(strict_types=1);

namespace App\Services;

use App\Models\HostApplication;
use App\Models\PolicyAcceptance;
use App\Models\SecurityAuditLog;
use App\Models\User;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;

class LegalComplianceService
{
    public const CURRENT_POLICY_VERSION = '2026.11-v1';

    /**
     * The account policies at their current version: the built-in text, replaced by the version an administrator
     * last published (PolicyCatalog). Form fields never change, so registration keeps working when a version is published.
     */
    public static function registrationPolicies(): array
    {
        return self::currentVersions(self::builtInRegistrationPolicies());
    }

    /** The host responsibility acknowledgements at their current version (see registrationPolicies). */
    public static function hostResponsibilityPolicies(): array
    {
        return self::currentVersions(self::builtInHostResponsibilityPolicies());
    }

    private static function currentVersions(array $specs): array
    {
        if (!function_exists('app')) {
            return $specs;
        }
        try {
            return app(\App\Services\Compliance\PolicyCatalog::class)->overlay($specs);
        } catch (\Throwable) {
            return $specs;
        }
    }

    /**
     * Mandatory individual policies required during account registration.
     *
     * @return array<string, array{field: string, policy_name: string, title: string, version: string, clauses: string[]}>
     */
    public static function builtInRegistrationPolicies(): array
    {
        return [
            'platform_terms_and_conditions' => [
                'field' => 'accept_platform_terms',
                'policy_name' => 'platform_terms_and_conditions',
                'title' => 'A. Platform Terms and Conditions',
                'version' => self::CURRENT_POLICY_VERSION,
                'clauses' => [
                    'Acceptance of EventBite\'s Terms of Service.',
                    'Understanding of the platform\'s role as an event discovery, ticketing and ordering platform.',
                    'Understanding that hosts are independently responsible for the legality and safety of their events.',
                    'Agreement not to use EventBite to facilitate unlawful activities.',
                ],
            ],
            'food_safety_disclaimer' => [
                'field' => 'accept_food_safety_disclaimer',
                'policy_name' => 'food_safety_disclaimer',
                'title' => 'B. Food Safety Disclaimer',
                'version' => self::CURRENT_POLICY_VERSION,
                'clauses' => [
                    'Safe food preparation and handling.',
                    'Proper cooking and holding temperatures.',
                    'Refrigeration and storage.',
                    'Prevention of cross-contamination.',
                    'Handwashing and sanitation.',
                    'Food transportation.',
                    'Allergen identification and disclosure.',
                    'Foodborne illness prevention.',
                    'Appropriate food-service permits and inspections.',
                    'Safe disposal of food and waste.',
                ],
            ],
            'alcohol_policy' => [
                'field' => 'accept_alcohol_policy',
                'policy_name' => 'alcohol_policy',
                'title' => 'C. Alcohol Policy',
                'version' => self::CURRENT_POLICY_VERSION,
                'clauses' => [
                    'Alcohol may only be sold, supplied or served when legally authorized.',
                    'Applicable state and local alcohol laws must be followed.',
                    'Hosts must obtain the necessary licences and permits.',
                    'Minimum legal drinking ages must be respected.',
                    'Valid identification and age verification procedures must be followed where required.',
                    'Alcohol must not be served to prohibited persons or in prohibited circumstances.',
                    'Responsible-service requirements must be observed.',
                    'Hosts are responsible for applicable alcohol-related insurance and legal obligations.',
                    'Purchasing an event ticket does not automatically authorize alcohol consumption and EventBite does not grant an alcohol licence.',
                ],
            ],
        ];
    }

    /**
     * Mandatory individual acknowledgements required when applying to become an Event Host
     * (Section D: Host Responsibility — individually selectable checkboxes, never combined into one).
     *
     * @return array<string, array{field: string, policy_name: string, title: string, version: string, description: string}>
     */
    public static function builtInHostResponsibilityPolicies(): array
    {
        return [
            'host_resp_independent_compliance' => [
                'field' => 'ack_independent_compliance',
                'policy_name' => 'host_resp_independent_compliance',
                'title' => 'Independent Legal & Municipal Compliance',
                'version' => self::CURRENT_POLICY_VERSION,
                'description' => 'I understand that as an Event Host I am independently responsible for the legality, safety, and compliance of my events with all applicable state, county, and municipal laws.',
            ],
            'host_resp_food_safety_and_temperatures' => [
                'field' => 'ack_food_safety_temperatures',
                'policy_name' => 'host_resp_food_safety_and_temperatures',
                'title' => 'Food Preparation, Holding Temperatures, Storage & Sanitation',
                'version' => self::CURRENT_POLICY_VERSION,
                'description' => 'I commit to safe food preparation, proper cooking and holding temperatures, refrigeration, handwashing, sanitation, safe food transport, foodborne illness prevention, and safe waste disposal.',
            ],
            'host_resp_allergen_and_cross_contamination' => [
                'field' => 'ack_allergen_disclosure',
                'policy_name' => 'host_resp_allergen_and_cross_contamination',
                'title' => 'Allergen Identification, Dietary Disclosures & Cross-Contamination Prevention',
                'version' => self::CURRENT_POLICY_VERSION,
                'description' => 'I will accurately disclose ingredients and major food allergens on all menu items and implement strict cross-contamination controls across food stations.',
            ],
            'host_resp_permits_and_inspections' => [
                'field' => 'ack_permits_and_inspections',
                'policy_name' => 'host_resp_permits_and_inspections',
                'title' => 'Food-Service Permits, Licences & Health Inspections',
                'version' => self::CURRENT_POLICY_VERSION,
                'description' => 'I will obtain and maintain all required food-service permits, vendor health certificates, and municipal inspections prior to serving food to attendees.',
            ],
            'host_resp_alcohol_licensing_and_age_verification' => [
                'field' => 'ack_alcohol_compliance',
                'policy_name' => 'host_resp_alcohol_licensing_and_age_verification',
                'title' => 'Alcohol Licensing, Age Verification & Responsible Service',
                'version' => self::CURRENT_POLICY_VERSION,
                'description' => 'I understand that EventBite does not grant an alcohol licence; alcohol may only be sold or served with valid state/local permits, strict government ID age verification, responsible service, and required liquor liability coverage.',
            ],
            'host_resp_identity_and_accountability' => [
                'field' => 'ack_identity_accountability',
                'policy_name' => 'host_resp_identity_and_accountability',
                'title' => 'Host Identity Verification & Operational Accountability',
                'version' => self::CURRENT_POLICY_VERSION,
                'description' => 'I certify that my submitted identity and organization information is accurate and accept accountability for all assigned event workers, food vendors, and on-site operations.',
            ],
        ];
    }

    /**
     * Validate that registration policy acknowledgements are accepted.
     *
     * @param array<string, mixed> $data
     * @param bool $requireExplicitIndividual
     * @throws \InvalidArgumentException
     */
    public static function assertRegistrationPoliciesAccepted(array $data, bool $requireExplicitIndividual = false): void
    {
        $policies = self::registrationPolicies();
        $anyIndividualKeyPresent = false;
        foreach ($policies as $spec) {
            if (array_key_exists($spec['field'], $data)) {
                $anyIndividualKeyPresent = true;
                break;
            }
        }

        if ($requireExplicitIndividual || $anyIndividualKeyPresent || !empty($data['enforce_policy_acknowledgements'])) {
            foreach ($policies as $spec) {
                $field = $spec['field'];
                if (empty($data[$field])) {
                    throw new \InvalidArgumentException("You must acknowledge and accept {$spec['title']} to complete registration.");
                }
            }
        }

        if (empty($data['agree_terms']) && !$anyIndividualKeyPresent) {
            throw new \InvalidArgumentException('You must agree to the platform terms and privacy policy.');
        }
    }

    /**
     * Persist auditable registration policy acceptance records for a newly registered user.
     *
     * @param User $user
     * @param array<string, mixed> $data
     * @param string|null $ipAddress
     * @param string|null $userAgent
     * @return array<int, PolicyAcceptance>
     */
    public static function recordRegistrationAcceptances(
        User $user,
        array $data,
        ?string $ipAddress = null,
        ?string $userAgent = null
    ): array {
        $acceptedAtUtc = gmdate('Y-m-d H:i:s');
        $resolvedIp = $ipAddress ?? (function_exists('request') && request() ? (request()->ip() ?? '127.0.0.1') : '127.0.0.1');
        $resolvedUa = substr(
            (string) ($userAgent ?? (function_exists('request') && request() ? (request()->userAgent() ?? 'WebBrowser/1.0') : 'WebBrowser/1.0')),
            0,
            512
        );

        $records = [];
        $canPersist = self::canUsePolicyAcceptancesTable();

        foreach (self::registrationPolicies() as $key => $spec) {
            $records[] = self::persistAcceptanceRecord([
                'user_id' => $user->id,
                'policy_name' => $spec['policy_name'],
                'policy_version' => $spec['version'],
                'acceptance_status' => 'accepted',
                'accepted_at_utc' => $acceptedAtUtc,
                'ip_address' => $resolvedIp,
                'user_agent' => $resolvedUa,
                'context' => 'account_registration',
                'context_entity_id' => (string) $user->id,
                'metadata' => [
                    'title' => $spec['title'],
                    'clauses_count' => count($spec['clauses']),
                    'explicit_field' => !empty($data[$spec['field']]),
                ],
            ], $canPersist);
        }

        // Also record if user checked optional host-intent responsibility during registration
        if (!empty($data['accept_host_responsibility'])) {
            $records[] = self::persistAcceptanceRecord([
                'user_id' => $user->id,
                'policy_name' => 'host_responsibility_pre_acknowledgement',
                'policy_version' => self::CURRENT_POLICY_VERSION,
                'acceptance_status' => 'accepted',
                'accepted_at_utc' => $acceptedAtUtc,
                'ip_address' => $resolvedIp,
                'user_agent' => $resolvedUa,
                'context' => 'account_registration',
                'context_entity_id' => (string) $user->id,
                'metadata' => [
                    'title' => 'D. Host Responsibility Acknowledgement',
                ],
            ], $canPersist);
        }

        return $records;
    }

    /**
     * Validate that a Host Application includes all required individual Host Responsibility acknowledgements
     * whenever the compliance form fields are submitted or strict enforcement is requested.
     *
     * @param array<string, mixed> $data
     * @param bool $requireExplicitIndividual
     * @throws \InvalidArgumentException
     */
    public static function assertHostApplicationPoliciesAccepted(array $data, bool $requireExplicitIndividual = false): void
    {
        $policies = self::hostResponsibilityPolicies();
        $anyHostAckPresent = false;
        foreach ($policies as $spec) {
            if (array_key_exists($spec['field'], $data)) {
                $anyHostAckPresent = true;
                break;
            }
        }

        if ($requireExplicitIndividual || $anyHostAckPresent || !empty($data['enforce_host_compliance'])) {
            foreach ($policies as $spec) {
                $field = $spec['field'];
                if (empty($data[$field])) {
                    throw new \InvalidArgumentException(
                        "You must individually acknowledge '{$spec['title']}' before submitting an Event Host application."
                    );
                }
            }
        }
    }

    /**
     * Persist auditable Host Responsibility policy acceptances for a Host Application.
     *
     * @param User $user
     * @param HostApplication $application
     * @param array<string, mixed> $data
     * @param string|null $ipAddress
     * @param string|null $userAgent
     * @return array<int, PolicyAcceptance>
     */
    public static function recordHostApplicationAcceptances(
        User $user,
        HostApplication $application,
        array $data,
        ?string $ipAddress = null,
        ?string $userAgent = null
    ): array {
        $acceptedAtUtc = gmdate('Y-m-d H:i:s');
        $resolvedIp = $ipAddress ?? (function_exists('request') && request() ? (request()->ip() ?? '127.0.0.1') : '127.0.0.1');
        $resolvedUa = substr(
            (string) ($userAgent ?? (function_exists('request') && request() ? (request()->userAgent() ?? 'WebBrowser/1.0') : 'WebBrowser/1.0')),
            0,
            512
        );

        $records = [];
        $canPersist = self::canUsePolicyAcceptancesTable();

        foreach (self::hostResponsibilityPolicies() as $spec) {
            // Only what the applicant actually ticked is recorded. An application sent without the acknowledgements
            // records none: the host accepts them later on their compliance page, and cannot publish until then.
            if (empty($data[$spec['field']])) {
                continue;
            }
            $records[] = self::persistAcceptanceRecord([
                'user_id' => $user->id,
                'policy_name' => $spec['policy_name'],
                'policy_version' => $spec['version'],
                'acceptance_status' => 'accepted',
                'accepted_at_utc' => $acceptedAtUtc,
                'ip_address' => $resolvedIp,
                'user_agent' => $resolvedUa,
                'context' => 'host_application',
                'context_entity_id' => (string) $application->id,
                'metadata' => [
                    'title' => $spec['title'],
                    'organization_name' => $application->organization_name,
                ],
            ], $canPersist);
        }

        SecurityAuditLog::create([
            'id' => (string) Str::uuid(),
            'user_id' => $user->id,
            'actor_role' => $user->getActiveRole()->value,
            'action' => 'host_compliance_policies_accepted',
            'target_entity' => 'host_applications',
            'target_id' => (string) $application->id,
            'ip_address' => $resolvedIp,
            'user_agent' => $resolvedUa,
            'details' => "User {$user->email} accepted " . count($records) . ' of ' . count(self::hostResponsibilityPolicies())
                . ' host responsibility acknowledgements with their host application' . ($records === [] ? '; none were ticked, so they must be accepted before publishing.' : '.'),
            'is_pii_access' => false,
            'created_at' => now(),
        ]);

        return $records;
    }

    /**
     * Mandatory event-level declarations required every time an event is created,
     * and whenever jurisdiction, food-service arrangements, or alcohol-service arrangements materially change.
     *
     * @return array<string, array{title: string, items: array<string, string>}>
     */
    public static function eventComplianceDeclarations(): array
    {
        return [
            'food_safety' => [
                'title' => 'Food safety',
                'items' => [
                    'decl_food_safety_laws' => 'I am responsible for ensuring that food preparation, handling, storage, transportation and serving comply with applicable laws.',
                    'decl_food_permits_inspections' => 'I will obtain required food permits, licences and inspections.',
                    'decl_food_handler_certification' => 'I will ensure that food handlers meet applicable training and certification requirements.',
                    'decl_food_allergen_accuracy' => 'I will provide accurate ingredient and allergen information.',
                    'decl_food_contamination_prevention' => 'I will take reasonable measures to prevent cross-contamination and foodborne illness.',
                ],
            ],
            'alcohol' => [
                'title' => 'Alcohol',
                'items' => [
                    'decl_alcohol_regulated' => 'I understand that alcohol sales, distribution and service are regulated.',
                    'decl_alcohol_authorized_only' => 'I will not advertise or provide alcohol service unless legally authorized.',
                    'decl_alcohol_licences_approvals' => 'I will obtain all required licences, permits and approvals.',
                    'decl_alcohol_age_verification' => 'I will comply with minimum-age requirements and applicable identification checks.',
                    'decl_alcohol_responsible_service' => 'I will follow responsible alcohol-service requirements.',
                    'decl_alcohol_event_restrictions' => 'I understand that alcohol authorization may be subject to additional event-specific restrictions.',
                ],
            ],
            'general_legal' => [
                'title' => 'General legal responsibility',
                'items' => [
                    'decl_general_accurate_info' => 'I have provided accurate event information.',
                    'decl_general_jurisdiction_compliance' => 'I will comply with applicable federal, state, county and municipal requirements.',
                    'decl_general_insurance_permits' => 'I will maintain any required insurance, permits and certifications.',
                    'decl_general_platform_enforcement' => 'I understand that EventBite may suspend or remove events that appear to violate its policies or applicable law.',
                ],
            ],
        ];
    }

    /**
     * Return flat list of all 15 mandatory event declaration input field names.
     *
     * @return list<string>
     */
    public static function eventComplianceDeclarationFieldNames(): array
    {
        $fields = [];
        foreach (self::eventComplianceDeclarations() as $group) {
            foreach (array_keys($group['items']) as $field) {
                $fields[] = $field;
            }
        }

        return $fields;
    }

    /**
     * Determine whether all 15 mandatory event declarations are accepted in the payload.
     *
     * @param array<string, mixed> $data
     */
    public static function hasAllEventDeclarationsAccepted(array $data): bool
    {
        foreach (self::eventComplianceDeclarationFieldNames() as $field) {
            if (empty($data[$field])) {
                return false;
            }
        }

        return true;
    }

    /**
     * Extract normalized compliance arrangement state (jurisdiction, food-service, alcohol-service) from an event.
     *
     * @param array<string, mixed> $overrides
     * @return array<string, mixed>
     */
    public static function extractComplianceState(\App\Models\Event $event, array $overrides = []): array
    {
        $country = strtoupper(trim((string) ($overrides['country_code'] ?? $event->country_code ?? 'US')));
        $state = strtoupper(trim((string) ($overrides['state_code'] ?? $event->state_code ?? '')));
        // A submitted value wins even when it is blank (the host cleared it); otherwise the event's own value.
        $pick = fn (string $key) => trim((string) (array_key_exists($key, $overrides) ? $overrides[$key] : ($event->{$key} ?? '')));
        $county = $pick('compliance_jurisdiction_county');
        // The municipality the host named; without one, the event's city.
        $municipality = $pick('compliance_jurisdiction_municipality');
        if ($municipality === '') {
            $municipality = $pick('city');
        }

        $supportsFood = array_key_exists('supports_food_ordering', $overrides)
            ? (bool) $overrides['supports_food_ordering']
            : ($event->supports_food_ordering !== false);
        $foodOrderingMode = trim((string) ($overrides['food_ordering_mode'] ?? $event->food_ordering_mode ?? 'preorder'));
        $foodFulfillment = trim((string) ($overrides['food_fulfillment'] ?? $event->food_fulfillment ?? 'pickup'));
        $foodPermitStatus = trim((string) ($overrides['food_permit_status'] ?? $event->food_permit_status ?? ''));
        $foodPermitNumber = trim((string) ($overrides['food_permit_number'] ?? $event->food_permit_number ?? ''));

        $servesAlcohol = array_key_exists('serves_alcohol', $overrides)
            ? (bool) $overrides['serves_alcohol']
            : (bool) $event->serves_alcohol;
        $alcoholAgeVerify = array_key_exists('alcohol_age_verification_required', $overrides)
            ? (bool) $overrides['alcohol_age_verification_required']
            : (bool) $event->alcohol_age_verification_required;
        $alcoholPermitStatus = trim((string) ($overrides['alcohol_permit_status'] ?? $event->alcohol_permit_status ?? ($servesAlcohol ? '' : 'no_alcohol')));
        $alcoholPermitNumber = trim((string) ($overrides['alcohol_permit_number'] ?? $event->alcohol_permit_number ?? ''));

        return [
            'jurisdiction_country' => $country,
            'jurisdiction_state' => $state,
            'jurisdiction_county' => $county,
            'jurisdiction_municipality' => $municipality,
            'supports_food_ordering' => $supportsFood,
            'food_ordering_mode' => $foodOrderingMode,
            'food_fulfillment' => $foodFulfillment,
            'food_permit_status' => $foodPermitStatus,
            'food_permit_number' => $foodPermitNumber,
            'serves_alcohol' => $servesAlcohol,
            'alcohol_age_verification_required' => $alcoholAgeVerify,
            'alcohol_permit_status' => $alcoholPermitStatus,
            'alcohol_permit_number' => $alcoholPermitNumber,
            // How and where food and alcohol are provided (EventRequirementChecklist::facts).
            ...self::arrangementState($event, $overrides),
        ];
    }

    /**
     * Compute a deterministic SHA-256 fingerprint of an event's jurisdiction, food-service, and alcohol-service arrangements.
     *
     * @param array<string, mixed> $state
     */
    public static function computeArrangementFingerprint(array $state): string
    {
        $normalized = [
            'country' => strtolower((string) ($state['jurisdiction_country'] ?? 'us')),
            'state' => strtolower((string) ($state['jurisdiction_state'] ?? '')),
            'county' => strtolower((string) ($state['jurisdiction_county'] ?? '')),
            'municipality' => strtolower((string) ($state['jurisdiction_municipality'] ?? '')),
            'supports_food_ordering' => (bool) ($state['supports_food_ordering'] ?? true),
            'food_ordering_mode' => strtolower((string) ($state['food_ordering_mode'] ?? 'preorder')),
            'food_fulfillment' => strtolower((string) ($state['food_fulfillment'] ?? 'pickup')),
            'food_permit_status' => strtolower((string) ($state['food_permit_status'] ?? '')),
            'food_permit_number' => strtolower((string) ($state['food_permit_number'] ?? '')),
            'serves_alcohol' => (bool) ($state['serves_alcohol'] ?? false),
            'alcohol_age_verification_required' => (bool) ($state['alcohol_age_verification_required'] ?? false),
            'alcohol_permit_status' => strtolower((string) ($state['alcohol_permit_status'] ?? '')),
            'alcohol_permit_number' => strtolower((string) ($state['alcohol_permit_number'] ?? '')),
            'food_arrangement' => (string) ($state['food_arrangement'] ?? ''),
            'alcohol_arrangement' => (string) ($state['alcohol_arrangement'] ?? ''),
        ];

        return hash('sha256', (string) json_encode($normalized));
    }

    /**
     * Detect whether an event's jurisdiction, food-service arrangements, or alcohol-service arrangements
     * materially changed between two states.
     *
     * @param array<string, mixed> $beforeState
     * @param array<string, mixed> $afterState
     * @return array{changed: bool, categories: list<string>, reasons: list<string>, summary: ?string}
     */
    public static function detectMaterialComplianceChanges(array $beforeState, array $afterState): array
    {
        $categories = [];
        $reasons = [];

        // 1. Jurisdiction comparison
        $beforeJurisdiction = strtolower(implode('|', [
            $beforeState['jurisdiction_country'] ?? 'US',
            $beforeState['jurisdiction_state'] ?? '',
            $beforeState['jurisdiction_county'] ?? '',
            $beforeState['jurisdiction_municipality'] ?? '',
        ]));
        $afterJurisdiction = strtolower(implode('|', [
            $afterState['jurisdiction_country'] ?? 'US',
            $afterState['jurisdiction_state'] ?? '',
            $afterState['jurisdiction_county'] ?? '',
            $afterState['jurisdiction_municipality'] ?? '',
        ]));
        if ($beforeJurisdiction !== $afterJurisdiction) {
            $categories[] = 'jurisdiction';
            $reasons[] = sprintf(
                'Applicable jurisdiction changed from (%s) to (%s)',
                trim(implode(', ', array_filter([$beforeState['jurisdiction_municipality'] ?? '', $beforeState['jurisdiction_county'] ?? '', $beforeState['jurisdiction_state'] ?? '', $beforeState['jurisdiction_country'] ?? '']))),
                trim(implode(', ', array_filter([$afterState['jurisdiction_municipality'] ?? '', $afterState['jurisdiction_county'] ?? '', $afterState['jurisdiction_state'] ?? '', $afterState['jurisdiction_country'] ?? ''])))
            );
        }

        // 2. Food-service arrangements comparison
        $foodChanged = (bool) ($beforeState['supports_food_ordering'] ?? true) !== (bool) ($afterState['supports_food_ordering'] ?? true)
            || strtolower((string) ($beforeState['food_ordering_mode'] ?? 'preorder')) !== strtolower((string) ($afterState['food_ordering_mode'] ?? 'preorder'))
            || strtolower((string) ($beforeState['food_fulfillment'] ?? 'pickup')) !== strtolower((string) ($afterState['food_fulfillment'] ?? 'pickup'))
            || strtolower((string) ($beforeState['food_permit_status'] ?? '')) !== strtolower((string) ($afterState['food_permit_status'] ?? ''))
            || strtolower((string) ($beforeState['food_permit_number'] ?? '')) !== strtolower((string) ($afterState['food_permit_number'] ?? ''))
            || (string) ($beforeState['food_arrangement'] ?? '') !== (string) ($afterState['food_arrangement'] ?? '');

        if ($foodChanged) {
            $categories[] = 'food_service';
            $reasons[] = 'Food-service arrangements or food permit disclosures materially changed';
        }

        // 3. Alcohol-service arrangements comparison
        $alcoholChanged = (bool) ($beforeState['serves_alcohol'] ?? false) !== (bool) ($afterState['serves_alcohol'] ?? false)
            || (bool) ($beforeState['alcohol_age_verification_required'] ?? false) !== (bool) ($afterState['alcohol_age_verification_required'] ?? false)
            || strtolower((string) ($beforeState['alcohol_permit_status'] ?? '')) !== strtolower((string) ($afterState['alcohol_permit_status'] ?? ''))
            || strtolower((string) ($beforeState['alcohol_permit_number'] ?? '')) !== strtolower((string) ($afterState['alcohol_permit_number'] ?? ''))
            || (string) ($beforeState['alcohol_arrangement'] ?? '') !== (string) ($afterState['alcohol_arrangement'] ?? '');

        if ($alcoholChanged) {
            $categories[] = 'alcohol_service';
            $reasons[] = 'Alcohol-service arrangements or alcohol permit disclosures materially changed';
        }

        $changed = $categories !== [];

        return [
            'changed' => $changed,
            'categories' => $categories,
            'reasons' => $reasons,
            'summary' => $changed ? implode('; ', $reasons) . '.' : null,
        ];
    }

    /**
     * How and where an event provides food and alcohol, as two comparable strings: whether food is sold or
     * complimentary, whether a licensed caterer is involved and where food is prepared; whether alcohol is
     * sold, included, complimentary or BYOB, whether a licensed alcohol vendor is involved and where it is served.
     *
     * @return array{food_arrangement: string, alcohol_arrangement: string}
     */
    private static function arrangementState(\App\Models\Event $event, array $overrides): array
    {
        $pick = fn (string $key) => array_key_exists($key, $overrides) ? $overrides[$key] : $event->{$key};
        $facts = \App\Services\Compliance\EventRequirementChecklist::facts([
            'category' => $pick('category'),
            'food_service_mode' => $pick('food_service_mode'),
            'alcohol_service_mode' => $pick('alcohol_service_mode'),
            'serves_alcohol' => $pick('serves_alcohol'),
            'supports_food_ordering' => $pick('supports_food_ordering') ?? true,
            'uses_licensed_caterer' => $pick('uses_licensed_caterer'),
            'uses_licensed_alcohol_vendor' => $pick('uses_licensed_alcohol_vendor'),
            'state_code' => $pick('state_code'),
            'compliance_jurisdiction_county' => $pick('compliance_jurisdiction_county'),
            'compliance_jurisdiction_municipality' => $pick('compliance_jurisdiction_municipality'),
            'city' => $pick('city'),
            'food_prepared_elsewhere' => $pick('food_prepared_elsewhere'),
            'food_prep_state_code' => $pick('food_prep_state_code'),
            'food_prep_county' => $pick('food_prep_county'),
            'food_prep_city' => $pick('food_prep_city'),
            'alcohol_served_elsewhere' => $pick('alcohol_served_elsewhere'),
            'alcohol_service_state_code' => $pick('alcohol_service_state_code'),
            'alcohol_service_county' => $pick('alcohol_service_county'),
            'alcohol_service_city' => $pick('alcohol_service_city'),
        ]);
        // A place other than the event location is compared on its own; at the event location it follows the jurisdiction.
        $where = fn (array $location) => strtolower(implode('|', array_map(fn ($part) => trim((string) $part), $location)));

        return [
            'food_arrangement' => implode(';', [$facts['food_mode'], $facts['caterer'] ? 'caterer' : '', ($pick('food_prepared_elsewhere') ? $where($facts['locations']['food_preparation']) : 'at-event')]),
            'alcohol_arrangement' => implode(';', [$facts['alcohol_mode'], $facts['alcohol_vendor'] ? 'vendor' : '', ($pick('alcohol_served_elsewhere') ? $where($facts['locations']['alcohol_service']) : 'at-event')]),
        ];
    }

    private static function canUsePolicyAcceptancesTable(): bool
    {
        try {
            if (class_exists(Schema::class) && method_exists(Schema::class, 'hasTable')) {
                return Schema::hasTable('policy_acceptances');
            }
        } catch (\Throwable) {
            // Standalone unit test runner fallback
        }
        return true;
    }

    /**
     * @param array<string, mixed> $attributes
     */
    private static function persistAcceptanceRecord(array $attributes, bool $canPersist): PolicyAcceptance
    {
        if ($canPersist) {
            try {
                return PolicyAcceptance::create($attributes);
            } catch (\Throwable) {
                // Fallback to in-memory model if table not migrated in an isolated test
            }
        }

        $model = new PolicyAcceptance();
        foreach ($attributes as $k => $v) {
            $model->{$k} = $v;
        }
        return $model;
    }
}

