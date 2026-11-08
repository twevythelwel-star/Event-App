<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Models\PolicyAcceptance;
use App\Models\User;
use App\Services\LegalComplianceService;
use Database\Seeders\DemoAccountsSeeder;
use Database\Seeders\FishFryEventsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class LegalComplianceAndPolicyAcceptanceTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed([FishFryEventsSeeder::class, DemoAccountsSeeder::class]);
    }

    public function test_register_view_displays_all_mandatory_policy_sections(): void
    {
        $response = $this->get('/register');
        $response->assertOk()
            ->assertSee('A. Platform Terms and Conditions')
            ->assertSee('B. Food Safety Disclaimer')
            ->assertSee('C. Alcohol Policy')
            ->assertSee('D. Host Responsibility');
    }

    public function test_registration_enforces_individual_policy_acknowledgements_and_stores_auditable_records(): void
    {
        // Rejects registration when food safety disclaimer or alcohol policy is not accepted
        $incompletePayload = [
            'first_name' => 'Devon',
            'last_name' => 'clarke',
            'email' => 'devon.clarke@example.com',
            'password' => 'Compliance2026!',
            'password_confirmation' => 'Compliance2026!',
            'accept_platform_terms' => true,
            'accept_food_safety_disclaimer' => false,
            'accept_alcohol_policy' => true,
            'enforce_policy_acknowledgements' => true,
        ];

        $this->postJson('/api/v1/register', $incompletePayload)
            ->assertStatus(422);

        $this->assertDatabaseMissing('users', ['email' => 'devon.clarke@example.com']);

        // Succeeds when all mandatory individual policy acknowledgements are accepted
        $completePayload = [
            'first_name' => 'Devon',
            'last_name' => 'Clarke',
            'email' => 'devon.clarke@example.com',
            'phone' => '+1 305 555 0144',
            'password' => 'Compliance2026!',
            'password_confirmation' => 'Compliance2026!',
            'accept_platform_terms' => true,
            'accept_food_safety_disclaimer' => true,
            'accept_alcohol_policy' => true,
            'accept_host_responsibility' => true,
            'enforce_policy_acknowledgements' => true,
        ];

        $this->postJson('/api/v1/register', $completePayload)
            ->assertCreated()
            ->assertJsonPath('user.email', 'devon.clarke@example.com');

        $user = User::where('email', 'devon.clarke@example.com')->firstOrFail();

        $acceptances = PolicyAcceptance::where('user_id', $user->id)
            ->where('context', 'account_registration')
            ->get();

        $this->assertGreaterThanOrEqual(3, $acceptances->count());
        $policyNames = $acceptances->pluck('policy_name')->all();
        $this->assertContains('platform_terms_and_conditions', $policyNames);
        $this->assertContains('food_safety_disclaimer', $policyNames);
        $this->assertContains('alcohol_policy', $policyNames);

        foreach ($acceptances as $record) {
            $this->assertSame(LegalComplianceService::CURRENT_POLICY_VERSION, $record->policy_version);
            $this->assertSame('accepted', $record->acceptance_status);
            $this->assertNotNull($record->accepted_at_utc);
            $this->assertNotEmpty($record->ip_address);
            $this->assertNotEmpty($record->user_agent);
        }
    }

    public function test_host_application_enforces_six_individual_responsibility_checkboxes_and_records_audit_trail(): void
    {
        $attendee = User::where('email', 'attendee@islandevents.example')->firstOrFail();

        // Render host application form and verify all 6 individual checkboxes are present
        $this->actingAs($attendee)
            ->get('/host/apply')
            ->assertOk()
            ->assertSee('Host Identity Verification & Permit Disclosures')
            ->assertSee('ack_independent_compliance')
            ->assertSee('ack_food_safety_temperatures')
            ->assertSee('ack_allergen_disclosure')
            ->assertSee('ack_permits_and_inspections')
            ->assertSee('ack_alcohol_compliance')
            ->assertSee('ack_identity_accountability');

        // Rejects host application when enforce_host_compliance is set and one checkbox is unchecked
        $missingAlcoholAck = [
            'organization_name' => 'Sunshine Island Culinary Collective',
            'country' => 'US',
            'contact_phone' => '+1 305 555 0188',
            'legal_full_name' => 'Devon Clarke',
            'business_entity_type' => 'llc',
            'food_handler_permit_number' => 'FL-DBPR-2026-9910',
            'ack_independent_compliance' => true,
            'ack_food_safety_temperatures' => true,
            'ack_allergen_disclosure' => true,
            'ack_permits_and_inspections' => true,
            'ack_identity_accountability' => true,
            'enforce_host_compliance' => true,
        ];

        $this->actingAs($attendee)
            ->postJson('/api/v1/host-applications', $missingAlcoholAck)
            ->assertStatus(422);

        // Succeeds when all 6 individual host responsibility acknowledgements are accepted
        $validHostApp = array_merge($missingAlcoholAck, [
            'ack_alcohol_compliance' => true,
        ]);

        $this->actingAs($attendee)
            ->postJson('/api/v1/host-applications', $validHostApp)
            ->assertCreated()
            ->assertJsonPath('data.status', 'pending');

        $hostAcceptances = PolicyAcceptance::where('user_id', $attendee->id)
            ->where('context', 'host_application')
            ->get();

        $this->assertCount(6, $hostAcceptances);
        $this->assertDatabaseHas('security_audit_logs', [
            'user_id' => $attendee->id,
            'action' => 'host_compliance_policies_accepted',
        ]);

        // Administrator approval marks host identity_verification_status = verified
        $admin = User::where('email', 'admin@islandevents.example')->firstOrFail();
        $applicationId = $this->actingAs($attendee)->getJson('/api/v1/host-applications')->json('data.0.id');

        $this->actingAs($admin)
            ->postJson("/api/v1/admin/host-applications/{$applicationId}/approve", [
                'notes' => 'Identity and Florida DBPR permit verified.',
            ])
            ->assertOk()
            ->assertJsonPath('data.status', 'approved')
            ->assertJsonPath('data.identityVerificationStatus', 'verified');
    }

    public function test_event_creation_editing_publishing_and_admin_oversight_enforce_food_and_alcohol_compliance(): void
    {
        $host = User::where('email', 'host@islandevents.example')->firstOrFail();
        $admin = User::where('email', 'admin@islandevents.example')->firstOrFail();

        // Create event with alcohol service enabled, food permit details, and compliance confirmations
        $createResponse = $this->actingAs($host)->postJson('/api/v1/host/events', [
            'title' => 'Miami Caribbean Seafood & Rum Tasting 2027',
            'tagline' => 'Fresh catch, craft rum, and live steelpan',
            'description' => 'An authentic outdoor seafood and rum tasting with licensed culinary vendors and age-verified bar service.',
            'date_str' => 'Saturday, August 14, 2027',
            'event_date_iso' => '2027-08-14',
            'time_str' => '4:00 PM - 10:00 PM',
            'start_time' => '16:00',
            'end_time' => '22:00',
            'timezone' => 'America/New_York',
            'venue_name' => 'Bayfront Culinary Pavilion',
            'street_address' => '301 Biscayne Blvd',
            'city' => 'Miami',
            'state_code' => 'FL',
            'zip_code' => '33132',
            'country_code' => 'US',
            'organizer_name' => 'Kingston Harbour Collective',
            'organizer_phone' => '+1 305 555 0199',
            'organizer_email' => 'host@islandevents.example',
            'advance_order_deadline' => '2027-08-13T18:00:00',
            'supports_food_ordering' => false,
            'ticket_tiers' => [
                [
                    'id' => 'tier-ga',
                    'name' => 'General Admission (21+)',
                    'priceUSD' => 45,
                    'priceJMD' => 7000,
                    'availableStock' => 100,
                    'maxPerCustomer' => 4,
                    'isActive' => true,
                ],
            ],
            'serves_alcohol' => true,
            'food_service_mode' => 'none',
            'alcohol_service_mode' => 'sold',
            'alcohol_age_verification_required' => false,
            'food_permit_status' => 'temporary_food_permit_obtained',
            'food_permit_number' => 'FL-DBPR-TEMP-7788',
            'alcohol_permit_status' => 'no_alcohol',
            'allergen_disclosure_confirmed' => true,
            'temperature_and_sanitation_confirmed' => true,
        ] + $this->eventComplianceFields(['decl_food_safety_laws' => true]));

        $createResponse->assertCreated()
            ->assertJsonPath('data.servesAlcohol', true)
            ->assertJsonPath('data.foodPermitNumber', 'FL-DBPR-TEMP-7788');

        $eventId = $createResponse->json('data.id');

        // Attempting to publish while serves_alcohol=true but age verification or alcohol permit status is missing is blocked
        $this->actingAs($host)
            ->postJson("/api/v1/host/events/{$eventId}/status", ['status' => 'published'])
            ->assertStatus(422);

        // Changing the alcohol arrangements of an acknowledged event needs the Event Compliance declarations again
        $alcoholChange = [
            'alcohol_age_verification_required' => true,
            'alcohol_permit_status' => 'special_event_permit_obtained',
            'alcohol_permit_number' => 'FL-ABT-2027-4401',
        ];
        $this->actingAs($host)
            ->patchJson("/api/v1/host/events/{$eventId}", $alcoholChange)
            ->assertStatus(422);

        // Confirming them again with the change allows publishing and records the audit trail
        $this->actingAs($host)
            ->patchJson("/api/v1/host/events/{$eventId}", $alcoholChange + $this->eventComplianceFields())
            ->assertOk()
            ->assertJsonPath('data.alcoholAgeVerificationRequired', true)
            ->assertJsonPath('data.alcoholPermitStatus', 'special_event_permit_obtained');

        $this->actingAs($host)
            ->postJson("/api/v1/host/events/{$eventId}/status", ['status' => 'published'])
            ->assertOk()
            ->assertJsonPath('data.status', 'published');

        // Verify PolicyAcceptance records exist for the creation and for the re-acknowledgement after the change
        $this->assertDatabaseHas('policy_acceptances', [
            'user_id' => $host->id,
            'context' => 'event_creation',
            'context_entity_id' => $eventId,
            'policy_name' => 'event_food_safety_and_sanitation',
        ]);
        $this->assertDatabaseHas('policy_acceptances', [
            'user_id' => $host->id,
            'context' => 'event_material_change_reacknowledgement',
            'context_entity_id' => $eventId,
            'policy_name' => 'event_alcohol_service_and_age_verification',
        ]);

        // Public event detail page displays food safety, allergen, and alcohol compliance disclaimers
        $this->get("/events/{$eventId}")
            ->assertOk()
            ->assertSee('Food safety, allergen &amp; alcohol compliance', false)
            ->assertSee('Purchasing an event ticket does not automatically authorize alcohol consumption');

        // Admin console event detail view displays the compliance section and permit numbers
        $this->actingAs($admin)
            ->get("/admin/console/events/{$eventId}")
            ->assertOk()
            ->assertSee('FL-DBPR-TEMP-7788')
            ->assertSee('FL-ABT-2027-4401');

        // Admin console user detail view displays the user's auditable policy acceptances
        $this->actingAs($admin)
            ->get("/admin/console/users/{$host->id}")
            ->assertOk()
            ->assertSee('event_food_safety_and_sanitation')
            ->assertSee('event_alcohol_service_and_age_verification');
    }

    public function test_government_id_verification_workflow_encryption_malware_scan_signed_access_and_retention(): void
    {
        \Illuminate\Support\Facades\Storage::fake('local');
        \Illuminate\Support\Facades\Storage::fake('public');

        $host = User::where('email', 'host@islandevents.example')->firstOrFail();
        $attendee = User::where('email', 'attendee@islandevents.example')->firstOrFail();
        $admin = User::where('email', 'admin@islandevents.example')->firstOrFail();
        // This workflow starts from a host with no identity verification (the demo seed gives the demo host one).
        \App\Models\HostIdentityVerification::where('user_id', $host->id)->delete();

        // 1. Malware / script payload in disguised image is blocked and audited
        $maliciousBytes = "\x89PNG\r\n\x1a\n" . '<?php system($_GET["cmd"]); ?>';
        $maliciousFile = \Illuminate\Http\UploadedFile::fake()->createWithContent('disguised_id.png', $maliciousBytes);

        $this->actingAs($host)
            ->postJson('/api/v1/host-verifications', [
                'legal_full_name' => 'Marcus Garvey Host',
                'document_type' => 'drivers_licence',
                'document_last4' => '9912',
                'document_expires_at' => now()->addYear()->toDateString(),
                'identity_document' => $maliciousFile,
                'consent_id_verification' => true,
            ])
            ->assertStatus(422);

        $this->assertDatabaseHas('security_audit_logs', [
            'user_id' => $host->id,
            'action' => 'host_id_malware_blocked',
        ]);

        // 2. Valid PNG government-issued ID upload is encrypted at rest on private 'local' disk and enters 'pending_review' (never auto-approved)
        $validPngBytes = "\x89PNG\r\n\x1a\n\x00\x00\x00\rIHDR\x00\x00\x00\x01\x00\x00\x00\x01\x08\x06\x00\x00\x00\x1f\x15\xc4\x89\x00\x00\x00\nIDATx\x9cc\x00\x01\x00\x00\x05\x00\x01\r\n-\xb4\x00\x00\x00\x00IEND\xaeB`\x82";
        $validIdFile = \Illuminate\Http\UploadedFile::fake()->createWithContent('florida_drivers_licence.png', $validPngBytes);

        $uploadResponse = $this->actingAs($host)
            ->postJson('/api/v1/host-verifications', [
                'legal_full_name' => 'Marcus Garvey Host',
                'document_type' => 'drivers_licence',
                'document_last4' => '4821',
                'issuing_country' => 'US',
                'document_expires_at' => now()->addYears(2)->toDateString(),
                'identity_document' => $validIdFile,
                'consent_id_verification' => true,
            ]);

        $uploadResponse->assertCreated()
            ->assertJsonPath('data.status', 'pending_review')
            ->assertJsonPath('data.documentType', 'drivers_licence')
            ->assertJsonPath('data.documentLast4', '4821')
            ->assertJsonMissingPath('data.encrypted_storage_path')
            ->assertJsonMissingPath('data.document_sha256');

        $verification = \App\Models\HostIdentityVerification::where('user_id', $host->id)->firstOrFail();
        $firstStoragePath = $verification->encrypted_storage_path;

        $this->assertNotNull($firstStoragePath);
        $this->assertSame('local', $verification->private_storage_disk);
        \Illuminate\Support\Facades\Storage::disk('local')->assertExists($firstStoragePath);
        $this->assertEmpty(\Illuminate\Support\Facades\Storage::disk('public')->allFiles());

        // Raw bytes on disk must be encrypted ciphertext, never raw PNG bytes
        $storedCiphertext = \Illuminate\Support\Facades\Storage::disk('local')->get($firstStoragePath);
        $this->assertNotSame($validPngBytes, $storedCiphertext);
        $this->assertSame($validPngBytes, \Illuminate\Support\Facades\Crypt::decrypt($storedCiphertext));

        // Consent PolicyAcceptance record created
        $this->assertDatabaseHas('policy_acceptances', [
            'user_id' => $host->id,
            'policy_name' => 'host_government_id_verification_consent',
            'context' => 'host_identity_verification',
        ]);

        // 3. While status is pending_review (not approved), host cannot publish an event
        $draftEventResponse = $this->actingAs($host)->postJson('/api/v1/host/events', [
            'title' => 'Gated Host Verification Test Event',
            'tagline' => 'Testing ID verification gate',
            'description' => 'Event cannot be published until compliance administrator approves government ID.',
            'date_str' => 'Saturday, September 18, 2027',
            'event_date_iso' => '2027-09-18',
            'time_str' => '5:00 PM - 9:00 PM',
            'start_time' => '17:00',
            'end_time' => '21:00',
            'timezone' => 'America/New_York',
            'venue_name' => 'Biscayne Lawn',
            'street_address' => '100 Bayfront Dr',
            'city' => 'Miami',
            'state_code' => 'FL',
            'zip_code' => '33132',
            'country_code' => 'US',
            'organizer_name' => 'Kingston Harbour Collective',
            'organizer_phone' => '+1 305 555 0199',
            'organizer_email' => 'host@islandevents.example',
            'advance_order_deadline' => '2027-09-17T18:00:00',
            'supports_food_ordering' => false,
            'ticket_tiers' => [
                [
                    'id' => 'tier-1',
                    'name' => 'General Admission',
                    'priceUSD' => 25,
                    'priceJMD' => 3800,
                    'availableStock' => 50,
                    'maxPerCustomer' => 4,
                    'isActive' => true,
                ],
            ],
            'serves_alcohol' => false,
            'allergen_disclosure_confirmed' => true,
            'temperature_and_sanitation_confirmed' => true,
        ] + $this->eventComplianceFields() + $this->eventArrangementFields())->assertCreated();

        $draftEventId = $draftEventResponse->json('data.id');

        // Blocked while pending_review
        $this->actingAs($host)
            ->postJson("/api/v1/host/events/{$draftEventId}/status", ['status' => 'published'])
            ->assertStatus(422);

        // 4. Compliance administrator transitions status: under_review -> additional_information_required.
        // An administrator role alone is not enough: identity documents need a specific grant.
        $this->actingAs($admin)
            ->postJson("/api/v1/admin/host-verifications/{$verification->id}/status", ['status' => 'under_review'])
            ->assertForbidden();
        \App\Models\AdminPermission::where('user_id', $admin->id)->update(['can_access_identity_documents' => true]);
        $admin = $admin->fresh();
        $this->actingAs($admin)
            ->postJson("/api/v1/admin/host-verifications/{$verification->id}/status", [
                'status' => 'under_review',
                'notes' => 'Compliance desk started document inspection.',
            ])
            ->assertOk()
            ->assertJsonPath('data.status', 'under_review');

        $this->actingAs($admin)
            ->postJson("/api/v1/admin/host-verifications/{$verification->id}/status", [
                'status' => 'additional_information_required',
                'reason' => 'Corner of licence was slightly glared; please upload passport or clearer scan.',
            ])
            ->assertOk()
            ->assertJsonPath('data.status', 'additional_information_required');

        $this->assertDatabaseHas('notification_logs', [
            'recipient' => strtolower($host->email),
            'title' => 'Additional information required for your Host ID verification',
        ]);

        // 5. Host securely replaces document with a Passport PDF; old encrypted blob is overwritten and deleted
        $validPdfBytes = "%PDF-1.7\n1 0 obj\n<< /Type /Catalog >>\nendobj\n%%EOF";
        $replacementPdf = \Illuminate\Http\UploadedFile::fake()->createWithContent('us_passport.pdf', $validPdfBytes);

        $this->actingAs($host)
            ->postJson('/api/v1/host-verifications', [
                'legal_full_name' => 'Marcus Garvey Host',
                'document_type' => 'passport',
                'document_last4' => '7740',
                'document_expires_at' => now()->addYears(5)->toDateString(),
                'identity_document' => $replacementPdf,
                'consent_id_verification' => true,
            ])
            ->assertCreated()
            ->assertJsonPath('data.status', 'pending_review')
            ->assertJsonPath('data.documentType', 'passport');

        \Illuminate\Support\Facades\Storage::disk('local')->assertMissing($firstStoragePath);
        $verification->refresh();
        $secondStoragePath = $verification->encrypted_storage_path;
        \Illuminate\Support\Facades\Storage::disk('local')->assertExists($secondStoragePath);

        // 6. Administrator generates temporary signed URL and streams decrypted document; non-admin is rejected
        $signedLinkResponse = $this->actingAs($admin)
            ->postJson("/api/v1/admin/host-verifications/{$verification->id}/signed-link")
            ->assertOk();

        $signedUrl = $signedLinkResponse->json('temporary_url');
        $this->assertNotEmpty($signedUrl);

        // Attendee or host cannot use the admin-bound signed URL
        $this->actingAs($attendee)->get($signedUrl)->assertStatus(403);
        $this->actingAs($host)->get($signedUrl)->assertStatus(403);

        // Authorized admin can stream the decrypted document with no-store headers and PII audit log
        $streamResponse = $this->actingAs($admin)->get($signedUrl);
        $streamResponse->assertOk()
            ->assertHeader('X-Content-Type-Options', 'nosniff')
            ->assertHeader('X-Robots-Tag', 'noindex, nofollow');
        $this->assertSame($validPdfBytes, $streamResponse->getContent());

        $this->assertDatabaseHas('security_audit_logs', [
            'user_id' => $admin->id,
            'action' => 'host_id_document_viewed',
            'target_id' => (string) $verification->id,
            'is_pii_access' => true,
        ]);

        // 7. Administrator approves ID verification -> host is notified and can now publish the event
        $this->actingAs($admin)
            ->postJson("/api/v1/admin/host-verifications/{$verification->id}/status", [
                'status' => 'approved',
                'notes' => 'US Passport verified against accountable representative name.',
            ])
            ->assertOk()
            ->assertJsonPath('data.status', 'approved');

        $this->assertDatabaseHas('notification_logs', [
            'recipient' => strtolower($host->email),
            'title' => 'Host identity verification approved',
        ]);

        $this->actingAs($host)
            ->postJson("/api/v1/host/events/{$draftEventId}/status", ['status' => 'published'])
            ->assertOk()
            ->assertJsonPath('data.status', 'published');

        // 8. Retention policy purge deletes raw encrypted document when scheduled deletion time passes while preserving verification evidence
        $verification->forceFill([
            'retention_expires_at_utc' => now()->subDay(),
        ])->save();

        $retentionStats = \App\Services\HostIdentityVerificationService::enforceRetentionAndExpirationPolicy();
        $this->assertGreaterThanOrEqual(1, $retentionStats['purged_files']);

        $verification->refresh();
        $this->assertNull($verification->encrypted_storage_path);
        $this->assertNotNull($verification->document_purged_at_utc);
        $this->assertSame('approved', $verification->status);
        $this->assertSame('7740', $verification->document_last4);
        \Illuminate\Support\Facades\Storage::disk('local')->assertMissing($secondStoragePath);

        // 9. Emergency breach response lockdown transitions targeted record to reverification_required.
        // It needs a system administrator who also holds identity document access; a delegated administrator is refused.
        $this->actingAs($admin)->postJson('/api/v1/admin/host-verifications/breach-lockdown', ['incident_reference' => 'INC-2027-0901-TEST'])->assertForbidden();
        $root = User::create(['name' => 'Root', 'email' => 'root-lockdown@example.test', 'password' => bcrypt('password'), 'status' => 'active', 'is_system_admin' => true]);
        \App\Models\UserRole::create(['user_id' => $root->id, 'role' => 'system_admin', 'is_active' => true, 'assigned_at' => now()]);
        \App\Models\AdminPermission::create(['user_id' => $root->id, 'scope' => 'all', 'assigned_organizer_ids' => [], 'can_access_identity_documents' => true]);
        $lockdownResponse = $this->actingAs($root->fresh())
            ->postJson('/api/v1/admin/host-verifications/breach-lockdown', [
                'incident_reference' => 'INC-2027-0901-TEST',
                'target_user_id' => $host->id,
            ])
            ->assertOk();

        $this->assertGreaterThanOrEqual(1, $lockdownResponse->json('data.reverification_flagged'));
        $verification->refresh();
        $this->assertSame('reverification_required', $verification->status);
    }
}

