@extends('layouts.app')

@section('title', 'Become a host — EventBite')
@section('page-title', 'Become a host')

@section('content')
<div class="eb-stack" style="gap: var(--layout-section-gap); max-width: 860px;">
    <x-page-header title="Become an event host"
                   description="Hosts publish events, sell tickets and take food orders. An administrator reviews every application; you keep your attendee account while you wait."
                   :breadcrumbs="['My tickets & orders' => route('attendee.dashboard'), 'Become a host' => null]" />

    @if($existingApplication && $existingApplication->isPending())
        <x-alert type="info" title="Your application is being reviewed">
            We received your application for "{{ $existingApplication->organization_name }}" on {{ $existingApplication->created_at->format('F j, Y') }}. You will see the host tools here once it is approved.
        </x-alert>
    @elseif($existingApplication && $existingApplication->isRejected())
        <x-alert type="warning" title="Your last application was not approved">
            Reason given: {{ $existingApplication->rejection_reason }}. You can update your details and apply again below.
        </x-alert>
    @endif

    @php
        $docTypes = $supportedDocumentTypes ?? \App\Services\HostIdentityVerificationService::supportedDocumentTypes();
        $idStatusValue = $identityVerification?->status ?? 'not_submitted';
        $idStatusEnum = \App\Enums\HostVerificationStatus::tryFrom($idStatusValue) ?? \App\Enums\HostVerificationStatus::NOT_SUBMITTED;
    @endphp

    <x-card title="Government-Issued Identity Verification Status"
            :subtitle="'Current status: ' . $idStatusEnum->label()">
        <div class="eb-stack" style="gap: 0.75rem;">
            <p class="eb-help-text" style="margin: 0;">
                Every Event Host must submit an acceptable government-issued identity document before receiving permission to publish an event. Uploaded documents are encrypted at rest in private storage, restricted strictly to compliance administrators, and retained only according to EventBite's legal retention schedule. Uploading a document never automatically verifies identity—an authorized compliance administrator reviews each submission.
            </p>
            @if($identityVerification)
                <dl class="eb-grid-3" style="margin: 0;">
                    <div><dt class="eb-text-label">Status</dt><dd style="margin: 0; font-weight: 700;">{{ $idStatusEnum->label() }}</dd></div>
                    <div><dt class="eb-text-label">Document type</dt><dd style="margin: 0;">{{ $identityVerification->documentTypeLabel() }}</dd></div>
                    <div><dt class="eb-text-label">Document reference</dt><dd style="margin: 0;">{{ $identityVerification->document_last4 ? 'Ending in ' . $identityVerification->document_last4 : '—' }}{{ $identityVerification->document_expires_at ? ' · Exp ' . $identityVerification->document_expires_at->format('Y-m-d') : '' }}</dd></div>
                </dl>
                @if($identityVerification->rejection_or_info_reason)
                    <x-alert type="warning" title="Identity verification notice">
                        {{ $identityVerification->rejection_or_info_reason }}
                    </x-alert>
                @endif
            @endif

            @if($existingApplication && $existingApplication->isPending() && $idStatusEnum !== \App\Enums\HostVerificationStatus::APPROVED)
                <form action="{{ route('host.verification.store') }}" method="POST" enctype="multipart/form-data" class="eb-stack" style="gap: 0.75rem; padding-top: 0.75rem; border-top: 1px solid var(--border-default, #cbd5e1);">
                    @csrf
                    <div class="eb-grid-3">
                        <x-form-field name="legal_full_name" label="Legal full name" :required="true"
                                      :value="$identityVerification?->legal_full_name ?? $existingApplication?->legal_full_name ?? Auth::user()->name" />
                        <div class="eb-field">
                            <label class="eb-label" for="replace_government_id_type">Government ID type <span aria-hidden="true">*</span></label>
                            <select id="replace_government_id_type" name="government_id_type" class="eb-input" required>
                                @foreach($docTypes as $typeKey => $typeMeta)
                                    <option value="{{ $typeKey }}" @selected(($identityVerification?->document_type ?? 'drivers_licence') === $typeKey)>{{ is_array($typeMeta) ? ($typeMeta['label'] ?? $typeKey) : $typeMeta }}</option>
                                @endforeach
                            </select>
                        </div>
                        <x-form-field name="government_id_last4" label="Last 4 characters of ID (optional)"
                                      :value="$identityVerification?->document_last4" placeholder="e.g. 4821" />
                    </div>
                    <div class="eb-grid-2">
                        <x-form-field name="document_expires_at" type="date" label="Document expiration date"
                                      :value="$identityVerification?->document_expires_at?->format('Y-m-d')" />
                        <div class="eb-field">
                            <label class="eb-label" for="replace_identity_document">Upload government-issued ID (JPEG, PNG, WebP, or PDF, max 5 MB) <span aria-hidden="true">*</span></label>
                            <input id="replace_identity_document" type="file" name="identity_document" class="eb-input" accept=".jpg,.jpeg,.png,.webp,.pdf" required>
                        </div>
                    </div>
                    <label style="display: flex; align-items: flex-start; gap: 0.6rem; font-size: 0.85rem;">
                        <input type="checkbox" name="consent_id_verification" value="1" required>
                        <span>I consent to EventBite securely encrypting and reviewing my government-issued ID for host identity verification in accordance with the retention and privacy policy.</span>
                    </label>
                    <div class="eb-cluster" style="justify-content: flex-end;">
                        <x-button type="submit" variant="primary">Upload / Replace Government ID</x-button>
                    </div>
                </form>
            @endif
        </div>
    </x-card>

    @if(!$existingApplication || !$existingApplication->isPending())
        <x-card title="Application" subtitle="Tell us who you are and what you plan to host.">
            <form action="{{ route('host.apply.submit') }}" method="POST" enctype="multipart/form-data" class="eb-stack" style="gap: var(--layout-form-gap);">
                @csrf
                <input type="hidden" name="country" value="US">
                <x-form-field name="organization_name" label="Organization or promoter name" :required="true"
                              :value="$existingApplication?->organization_name" placeholder="e.g. Riverside Cultural Foundation"
                              help="Shown on your event pages, tickets and receipts." />
                <div class="eb-grid-2">
                    <x-form-field name="contact_phone" type="tel" label="Contact phone" :required="true"
                                  :value="$existingApplication?->contact_phone ?? Auth::user()->phone" placeholder="+1 305 555 0100" />
                    <x-form-field name="website_or_social" label="Website or social profile (optional)"
                                  :value="$existingApplication?->website_or_social" placeholder="https://instagram.com/yourorg" />
                </div>
                <x-form-field name="description" type="textarea" label="What will you host?" :rows="4"
                              :value="$existingApplication?->description"
                              help="The kind of events, how often, and roughly how many guests." />

                <div class="eb-card" style="padding: 1.25rem; background: var(--surface-subtle, #f8fafc); border: 1px solid var(--border-default, #cbd5e1); border-radius: 12px;">
                    <h3 style="margin: 0 0 0.5rem; font-size: 1rem; font-weight: 800;">Host Identity Verification &amp; Permit Disclosures</h3>
                    <p class="eb-help-text" style="margin: 0 0 1rem;">Verify your legal identity, upload your government-issued ID for encrypted compliance review, and list any applicable food-handler or alcohol permits for your events.</p>
                    <div class="eb-grid-2">
                        <x-form-field name="legal_full_name" label="Legal full name of accountable representative" :required="true"
                                      :value="$existingApplication?->legal_full_name ?? Auth::user()->name" placeholder="Full legal name on government ID" />
                        <x-form-field name="business_entity_type" label="Entity type"
                                      :value="$existingApplication?->business_entity_type ?? 'individual'" placeholder="Individual, LLC, Corporation, Nonprofit" />
                    </div>
                    <div class="eb-grid-3" style="margin-top: 0.75rem;">
                        <div class="eb-field">
                            <label class="eb-label" for="government_id_type">Government-issued ID type</label>
                            <select id="government_id_type" name="government_id_type" class="eb-input">
                                @foreach($docTypes as $typeKey => $typeMeta)
                                    <option value="{{ $typeKey }}" @selected(($existingApplication?->government_id_type ?? 'drivers_licence') === $typeKey)>{{ is_array($typeMeta) ? ($typeMeta['label'] ?? $typeKey) : $typeMeta }}</option>
                                @endforeach
                            </select>
                        </div>
                        <x-form-field name="government_id_last4" label="Last 4 digits of ID (do not enter full ID number)"
                                      :value="$existingApplication?->government_id_last4" placeholder="e.g. 4821" />
                        <x-form-field name="document_expires_at" type="date" label="ID expiration date"
                                      :value="$identityVerification?->document_expires_at?->format('Y-m-d')" />
                    </div>
                    <div class="eb-field" style="margin-top: 0.75rem;">
                        <label class="eb-label" for="identity_document">Government-issued ID document (JPEG, PNG, WebP, or PDF — max 5 MB, encrypted in private storage)</label>
                        <input id="identity_document" type="file" name="identity_document" class="eb-input" accept=".jpg,.jpeg,.png,.webp,.pdf">
                        <p class="eb-help-text" style="margin: 0.25rem 0 0;">Supported documents: Driver's licence, State-issued identification card, Passport, or other compliance-approved government ID. Never publicly accessible.</p>
                    </div>
                    <div class="eb-grid-2" style="margin-top: 0.75rem;">
                        <x-form-field name="food_handler_permit_number" label="Food-service / health permit # (if applicable)"
                                      :value="$existingApplication?->food_handler_permit_number" placeholder="e.g. FL-DBPR-2026-8841" />
                        <x-form-field name="alcohol_license_number" label="Alcohol / special-event permit # (if applicable)"
                                      :value="$existingApplication?->alcohol_license_number" placeholder="e.g. ABT-TEMP-4410" />
                    </div>
                </div>

                <div class="eb-card" id="host-responsibility-acknowledgements" style="padding: 1.25rem; background: var(--surface-subtle, #f8fafc); border: 1px solid var(--border-default, #cbd5e1); border-radius: 12px;">
                    <input type="hidden" name="enforce_host_compliance" value="1">
                    <h3 style="margin: 0 0 0.35rem; font-size: 1rem; font-weight: 800;">Mandatory Host Responsibility Acknowledgements (Section D)</h3>
                    <p class="eb-help-text" style="margin: 0 0 0.85rem;">Each responsibility below must be individually acknowledged before submitting your Event Host application. Read the full <a href="{{ route('legal.show', 'host-agreement') }}" target="_blank" rel="noopener">Event Host Agreement</a> and the <a href="{{ route('legal.show', 'host-identity-privacy') }}" target="_blank" rel="noopener">Host Identity Verification and Privacy Notice</a>.</p>
                    @unless(\App\Support\LegalPages::counselReviewed())
                        <p class="eb-help-text" style="margin: 0 0 0.85rem; color: #92400e;">These acknowledgements reflect draft policy wording pending review by qualified US legal counsel; they are not yet legally approved.</p>
                    @endunless

                    <div class="eb-stack" style="gap: 0.65rem;">
                        <label style="display: flex; align-items: flex-start; gap: 0.6rem; font-size: 0.88rem;">
                            <input type="checkbox" name="ack_independent_compliance" value="1" required>
                            <span><strong>1. Independent Legal &amp; Municipal Compliance *</strong> — I understand that as an Event Host I am independently responsible for the legality and safety of my events and compliance with state, county, and municipal laws.</span>
                        </label>
                        <label style="display: flex; align-items: flex-start; gap: 0.6rem; font-size: 0.88rem;">
                            <input type="checkbox" name="ack_food_safety_temperatures" value="1" required>
                            <span><strong>2. Safe Food Preparation, Temperatures, Storage &amp; Sanitation *</strong> — I commit to safe food preparation, proper cooking and holding temperatures, refrigeration, handwashing, sanitation, safe food transportation, foodborne illness prevention, and safe disposal of food and waste.</span>
                        </label>
                        <label style="display: flex; align-items: flex-start; gap: 0.6rem; font-size: 0.88rem;">
                            <input type="checkbox" name="ack_allergen_disclosure" value="1" required>
                            <span><strong>3. Allergen Identification, Dietary Disclosures &amp; Cross-Contamination *</strong> — I will accurately identify and disclose major food allergens and dietary classifications and enforce cross-contamination prevention across all food stations.</span>
                        </label>
                        <label style="display: flex; align-items: flex-start; gap: 0.6rem; font-size: 0.88rem;">
                            <input type="checkbox" name="ack_permits_and_inspections" value="1" required>
                            <span><strong>4. Food-Service Permits, Licences &amp; Inspections *</strong> — I will obtain and maintain all required food-service permits, health certificates, and municipal inspections prior to serving food.</span>
                        </label>
                        <label style="display: flex; align-items: flex-start; gap: 0.6rem; font-size: 0.88rem;">
                            <input type="checkbox" name="ack_alcohol_compliance" value="1" required>
                            <span><strong>5. Alcohol Policy, Age Verification &amp; Licensing *</strong> — I understand that EventBite does not grant an alcohol licence; alcohol may only be sold or served when legally authorized with valid permits, strict government ID age verification, responsible service, and required alcohol liability coverage.</span>
                        </label>
                        <label style="display: flex; align-items: flex-start; gap: 0.6rem; font-size: 0.88rem;">
                            <input type="checkbox" name="ack_identity_accountability" value="1" required>
                            <span><strong>6. Host Identity Verification &amp; Accountability *</strong> — I certify that my identity and organization details are accurate and accept full accountability for assigned event workers, food vendors, and on-site compliance.</span>
                        </label>
                    </div>
                </div>

                <p class="eb-help-text" style="margin: 0;">EventBite currently supports events in the United States, priced in US dollars.</p>
                <div class="eb-cluster" style="justify-content: flex-end;">
                    <x-button variant="ghost" :href="route('attendee.dashboard')">Cancel</x-button>
                    <x-button type="submit" variant="primary">Submit application</x-button>
                </div>
            </form>
        </x-card>
    @endif
</div>
@endsection
