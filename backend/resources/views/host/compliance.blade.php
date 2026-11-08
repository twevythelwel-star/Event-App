@extends('layouts.app')

@section('title', 'Compliance and verification — EventBite')
@section('page-title', 'Compliance and verification')

@php
    // Every status shows a text label and an icon as well as a colour, so it never depends on colour alone.
    $badge = fn (string $tone) => match ($tone) {
        'success' => 'eb-badge-success', 'warning' => 'eb-badge-warning', 'error' => 'eb-badge-error', 'info' => 'eb-badge-info', default => '',
    };
    $icon = fn (string $tone) => match ($tone) { 'success' => '✓', 'error' => '!', 'warning' => '!', 'info' => '…', default => '•' };
    $alertType = fn (string $tone) => match ($tone) { 'success' => 'success', 'error' => 'error', 'warning' => 'warning', default => 'info' };
    $policyGaps = collect($policies)->where('state', '!=', 'accepted');
    $sections = [
        'identity' => 'Identity',
        'acknowledgements' => 'Acknowledgements (' . count($acknowledgements) . ')',
        'policies' => 'Policy updates (' . $policyGaps->count() . ')',
        'events-needing' => 'Events needing information (' . count($events_needing_information) . ')',
        'events' => 'Event compliance',
        'documents' => 'Licences and permits',
        'expiring' => 'Expirations (' . count($expiring) . ')',
        'messages' => 'Review messages',
    ];
@endphp

@push('head')
<style>
    /* Plain links inside the compliance area use the accent text colour, readable in light and dark themes. */
    .eb-compliance a:not([class]) { color: var(--color-accent); text-decoration: underline; text-underline-offset: 2px; font-weight: 600; }
    .eb-compliance-nav a { color: var(--color-text-primary); border: 1px solid var(--color-accent-border); }
</style>
@endpush

@section('content')
<div class="eb-stack eb-compliance" style="gap: var(--layout-section-gap);">
    <x-page-header
        title="Compliance and verification"
        badge="Event host"
        description="Your identity verification, policies, permits and licences, and what each of your events still needs before it can be published.">
        <x-slot:actions>
            <x-button variant="secondary" :href="route('host.dashboard')">Back to dashboard</x-button>
        </x-slot:actions>
    </x-page-header>

    <x-alert :flash="true" />
    @if(session('status'))<x-alert type="success">{{ session('status') }}</x-alert>@endif
    @if($errors->any())
        <x-alert type="error" title="Please check the form">
            <ul style="margin: 0; padding-left: 1.1rem;">@foreach($errors->all() as $error)<li>{{ $error }}</li>@endforeach</ul>
        </x-alert>
    @endif

    {{-- Overall standing --}}
    <section id="account" aria-labelledby="account-title" class="eb-card" style="padding: 1.25rem; border-left: 4px solid var(--color-{{ ['success' => 'success', 'error' => 'error', 'warning' => 'warning'][$account['tone']] ?? 'info' }}, currentColor);">
        <div class="eb-cluster" style="justify-content: space-between; align-items: flex-start; gap: 1rem;">
            <div>
                <p class="eb-help-text" style="margin: 0;">Host account compliance</p>
                <h2 id="account-title" class="eb-text-section-title" style="margin: 0.15rem 0 0.35rem;">
                    <span class="eb-badge {{ $badge($account['tone']) }}" data-compliance-account="{{ $account['state'] }}"><span aria-hidden="true">{{ $icon($account['tone']) }}</span> {{ $account['label'] }}</span>
                </h2>
                <p class="eb-text-supporting" style="margin: 0;">{{ $account['summary'] }}</p>
            </div>
            <div class="eb-cluster" style="gap: 0.5rem;">
                <span class="eb-badge {{ $badge($identity['tone']) }}">ID: {{ $identity['label'] }}</span>
                <span class="eb-badge {{ $policyGaps->isEmpty() ? 'eb-badge-success' : 'eb-badge-warning' }}">Policies: {{ $policyGaps->isEmpty() ? 'All accepted' : $policyGaps->count() . ' to accept' }}</span>
                <span class="eb-badge {{ count($events_needing_information) ? 'eb-badge-warning' : 'eb-badge-success' }}">Events needing information: {{ count($events_needing_information) }}</span>
            </div>
        </div>
        @if($account['todo'])
            <h3 style="font-size: 0.95rem; margin: 1rem 0 0.4rem;">What you need to do</h3>
            <ol style="margin: 0; padding-left: 1.25rem;" class="eb-stack" data-compliance-todo>
                @foreach($account['todo'] as $todo)
                    <li style="margin: 0;">{{ $todo['text'] }} <a href="{{ $todo['url'] }}">Go there</a></li>
                @endforeach
            </ol>
        @endif
    </section>

    <nav aria-label="Compliance sections" class="eb-cluster eb-compliance-nav" style="gap: 0.4rem; flex-wrap: wrap;">
        @foreach($sections as $id => $label)
            <a class="eb-badge" href="#{{ $id }}">{{ $label }}</a>
        @endforeach
    </nav>

    {{-- Identity --}}
    <section id="identity" aria-labelledby="identity-title" class="eb-stack">
        <div class="eb-section-header"><div>
            <h2 id="identity-title" class="eb-text-section-title" style="margin: 0;">Identity verification</h2>
            <p class="eb-text-supporting" style="margin: 0;">EventBite checks a government-issued photo ID for every host before their events can be published.</p>
        </div></div>
        <div class="eb-card eb-stack" style="padding: 1.25rem;">
            <div class="eb-cluster" style="gap: 0.75rem; align-items: center;">
                <span class="eb-badge {{ $badge($identity['tone']) }}" data-identity-status="{{ $identity['state'] }}"><span aria-hidden="true">{{ $icon($identity['tone']) }}</span> {{ $identity['label'] }}</span>
                <span>{{ $identity['explain'] }}</span>
            </div>
            @if($identity['record'])
                <dl class="eb-grid-3" style="margin: 0;">
                    <div><dt class="eb-help-text">Document</dt><dd style="margin: 0;">{{ $identity['record']->documentTypeLabel() }}</dd></div>
                    <div><dt class="eb-help-text">Submitted</dt><dd style="margin: 0;">{{ $identity['record']->submitted_at_utc?->format('j M Y') ?? '—' }}</dd></div>
                    <div><dt class="eb-help-text">Document expires</dt><dd style="margin: 0;">{{ $identity['record']->document_expires_at?->format('j M Y') ?? 'Not given' }}</dd></div>
                </dl>
            @endif
            @if($identity['action'])
                <x-alert :type="$alertType($identity['tone'])" title="What to do">{{ $identity['action'] }}</x-alert>
            @endif
            @if($identity['can_upload'])
                <form action="{{ route('host.verification.store') }}" method="POST" enctype="multipart/form-data" class="eb-stack" style="gap: 0.75rem; padding-top: 0.75rem; border-top: 1px solid var(--color-border);">
                    @csrf
                    <h3 style="font-size: 0.95rem; margin: 0;">Upload your government ID</h3>
                    <div class="eb-grid-3">
                        <x-form-field name="legal_full_name" label="Legal full name, as on the ID" :required="true" :value="old('legal_full_name', $identity['record']?->legal_full_name ?? auth()->user()->name)" />
                        <div class="eb-field">
                            <label class="eb-label" for="compliance_id_type">ID type <span aria-hidden="true">*</span></label>
                            <select id="compliance_id_type" name="document_type" class="eb-input" required>
                                @foreach($documentTypes as $key => $meta)
                                    <option value="{{ $key }}" @selected(old('document_type', $identity['record']?->document_type) === $key)>{{ is_array($meta) ? ($meta['label'] ?? $key) : $meta }}</option>
                                @endforeach
                            </select>
                        </div>
                        <x-form-field name="document_expires_at" type="date" label="ID expiry date" :value="old('document_expires_at')" help="EventBite reminds you here before it expires." />
                    </div>
                    <div class="eb-field">
                        <label class="eb-label" for="compliance_id_file">ID document (JPEG, PNG, WebP or PDF, up to 5 MB) <span aria-hidden="true">*</span></label>
                        <input id="compliance_id_file" type="file" name="identity_document" class="eb-input" accept=".jpg,.jpeg,.png,.webp,.pdf" required>
                    </div>
                    <label style="display: flex; gap: 0.6rem; align-items: flex-start; font-size: 0.9rem;">
                        <input type="checkbox" name="consent_id_verification" value="1" required>
                        <span>I consent to EventBite encrypting and reviewing my government ID to verify my identity as a host, under the retention and privacy policy.</span>
                    </label>
                    <div><x-button type="submit" variant="primary">Upload ID for review</x-button></div>
                </form>
            @endif
        </div>
    </section>

    {{-- Outstanding acknowledgements --}}
    <section id="acknowledgements" aria-labelledby="ack-title" class="eb-stack">
        <div class="eb-section-header"><div>
            <h2 id="ack-title" class="eb-text-section-title" style="margin: 0;">Outstanding acknowledgements</h2>
            <p class="eb-text-supporting" style="margin: 0;">Statements only you can make. No one at EventBite can make them for you.</p>
        </div></div>
        <x-data-table caption="Outstanding acknowledgements" :columns="['Acknowledgement', 'For', 'Status', 'What to do']"
                      :empty="$acknowledgements === []" empty-title="Nothing outstanding" empty-text="Every host and event acknowledgement is up to date.">
            @foreach($acknowledgements as $row)
                <tr>
                    <td style="font-weight: 600;">{{ $row['title'] }}</td>
                    <td>{{ $row['scope'] }}</td>
                    <td><span class="eb-badge {{ $badge($row['tone']) }}"><span aria-hidden="true">{{ $icon($row['tone']) }}</span> {{ $row['label'] }}</span></td>
                    <td>{{ $row['action'] }} <a href="{{ $row['url'] }}">Go there</a></td>
                </tr>
            @endforeach
        </x-data-table>
    </section>

    {{-- Policy updates --}}
    <section id="policies" aria-labelledby="policies-title" class="eb-stack">
        <div class="eb-section-header"><div>
            <h2 id="policies-title" class="eb-text-section-title" style="margin: 0;">Policy updates requiring acceptance</h2>
            <p class="eb-text-supporting" style="margin: 0;">When EventBite changes a policy you accepted, you must read and accept the new version. Tick each policy separately.</p>
        </div></div>
        @if($policyGaps->isEmpty())
            <x-alert type="success">You have accepted the current version of every policy.</x-alert>
        @else
            <form method="POST" action="{{ route('host.compliance.policies') }}" class="eb-card eb-stack" style="padding: 1.25rem;">
                @csrf
                @foreach($policyGaps as $policy)
                    <div style="padding-bottom: 0.75rem; border-bottom: 1px solid var(--color-border);">
                        <div class="eb-cluster" style="gap: 0.5rem; align-items: center;">
                            <span class="eb-badge {{ $badge($policy['tone']) }}"><span aria-hidden="true">{{ $icon($policy['tone']) }}</span> {{ $policy['label'] }}</span>
                            <span class="eb-help-text">{{ $policy['group'] }} · version {{ $policy['version'] }}@if($policy['previous_version']) (you accepted {{ $policy['previous_version'] }})@endif</span>
                        </div>
                        <label style="display: flex; gap: 0.6rem; align-items: flex-start; margin-top: 0.5rem;">
                            <input type="checkbox" name="policies[]" value="{{ $policy['policy_name'] }}">
                            <span><strong>{{ $policy['title'] }}</strong> — {{ $policy['description'] }}</span>
                        </label>
                    </div>
                @endforeach
                <div><x-button type="submit" variant="primary">Accept the ticked policies</x-button></div>
            </form>
        @endif
        <details>
            <summary>All policies and when you accepted them</summary>
            <x-data-table caption="All policies" :columns="['Policy', 'Type', 'Version', 'Status', 'Accepted']">
                @foreach($policies as $policy)
                    <tr>
                        <td>{{ $policy['title'] }}</td>
                        <td>{{ $policy['group'] }}</td>
                        <td>{{ $policy['version'] }}</td>
                        <td><span class="eb-badge {{ $badge($policy['tone']) }}">{{ $policy['label'] }}</span></td>
                        <td>{{ $policy['accepted_at']?->format('j M Y') ?? '—' }}</td>
                    </tr>
                @endforeach
            </x-data-table>
        </details>
    </section>

    {{-- Events requiring additional information --}}
    <section id="events-needing" aria-labelledby="needing-title" class="eb-stack">
        <div class="eb-section-header"><div>
            <h2 id="needing-title" class="eb-text-section-title" style="margin: 0;">Events requiring additional information</h2>
            <p class="eb-text-supporting" style="margin: 0;">These events cannot be published until you give what is listed.</p>
        </div></div>
        @forelse($events_needing_information as $row)
            <div class="eb-card eb-stack" style="padding: 1rem 1.25rem; gap: 0.5rem;">
                <div class="eb-cluster" style="justify-content: space-between;">
                    <a href="{{ route('events.edit', $row['event']->id) }}" style="font-weight: 700; color: var(--color-text-primary);">{{ $row['event']->title }}</a>
                    <span class="eb-badge {{ $badge($row['tone']) }}"><span aria-hidden="true">{{ $icon($row['tone']) }}</span> {{ $row['label'] }}</span>
                </div>
                <ul style="margin: 0; padding-left: 1.1rem;">
                    @foreach($row['issues'] as $issue)
                        @unless($issue['waiting'])
                            <li>{{ $issue['message'] }} <a href="{{ $issue['url'] }}">{{ $issue['link'] }}</a></li>
                        @endunless
                    @endforeach
                </ul>
            </div>
        @empty
            <x-alert type="success">None of your events is missing information.</x-alert>
        @endforelse
    </section>

    {{-- Event-specific compliance status --}}
    <section id="events" aria-labelledby="events-title" class="eb-stack">
        <div class="eb-section-header"><div>
            <h2 id="events-title" class="eb-text-section-title" style="margin: 0;">Event compliance status</h2>
            <p class="eb-text-supporting" style="margin: 0;">Each current event, checked against the same rules used when it is published.</p>
        </div></div>
        <x-data-table caption="Event compliance status" :columns="['Event', 'Event status', 'Compliance', 'Details', ['label' => 'Actions', 'numeric' => true]]"
                      :empty="$events === []" empty-title="No current events" empty-text="Events you create appear here with their compliance status.">
            @foreach($events as $row)
                <tr data-event-compliance="{{ $row['event']->id }}">
                    <td style="font-weight: 600;">{{ $row['event']->title }}<div class="eb-help-text">{{ $row['event']->date_str }}</div></td>
                    <td><x-status-indicator :status="$row['event']->status ?? 'draft'" /></td>
                    <td><span class="eb-badge {{ $badge($row['tone']) }}"><span aria-hidden="true">{{ $icon($row['tone']) }}</span> {{ $row['label'] }}</span></td>
                    <td style="font-size: 0.85rem;">
                        @if($row['state'] === 'ready')
                            Nothing outstanding.
                        @elseif($row['state'] === 'account')
                            This event is complete, but your account items above must be resolved first.
                        @else
                            {{ count($row['issues']) }} {{ \Illuminate\Support\Str::plural('item', count($row['issues'])) }}:
                            {{ collect($row['issues'])->where('waiting', true)->count() }} waiting for EventBite, {{ collect($row['issues'])->where('waiting', false)->count() }} for you.
                        @endif
                        @foreach($row['overridden'] as $o)
                            <div class="eb-help-text">Temporarily waived until {{ $o['until']->format('j M Y') }}: {{ $o['message'] }}</div>
                        @endforeach
                    </td>
                    <td class="eb-table-num" style="white-space: nowrap;">
                        <x-button variant="secondary" size="sm" :href="route('events.requirements', $row['event']->id)">Requirements</x-button>
                        <x-button variant="ghost" size="sm" :href="route('events.edit', $row['event']->id) . '#studio-event-compliance'">Declarations</x-button>
                    </td>
                </tr>
            @endforeach
        </x-data-table>
    </section>

    {{-- Licences and permits --}}
    <section id="documents" aria-labelledby="docs-title" class="eb-stack">
        <div class="eb-section-header"><div>
            <h2 id="docs-title" class="eb-text-section-title" style="margin: 0;">Uploaded licences and permits</h2>
            <p class="eb-text-supporting" style="margin: 0;">A permit or licence counts only once an EventBite administrator has verified it.</p>
        </div></div>
        <x-data-table caption="Uploaded licences and permits" :columns="['Document', 'Event', 'Reference', 'Expires', 'Status', 'What to do']"
                      :empty="$documents === []" empty-title="Nothing uploaded yet" empty-text="Permits and licences you submit from an event's requirements checklist appear here.">
            @foreach($documents as $doc)
                <tr>
                    <td style="font-weight: 600;">{{ $doc['evidence']->item_title }}@if($doc['evidence']->document_name)<div class="eb-help-text">{{ $doc['evidence']->document_name }}</div>@endif</td>
                    <td>{{ $doc['evidence']->event?->title }}</td>
                    <td>{{ $doc['evidence']->reference_number ?: '—' }}<div class="eb-help-text">{{ $doc['evidence']->issuing_authority }}</div></td>
                    <td>{{ $doc['evidence']->expires_on?->format('j M Y') ?? 'Not given' }}</td>
                    <td><span class="eb-badge {{ $badge($doc['tone']) }}"><span aria-hidden="true">{{ $icon($doc['tone']) }}</span> {{ $doc['label'] }}</span></td>
                    <td>
                        @if($doc['action']){{ $doc['action'] }} <a href="{{ $doc['url'] }}">Open the checklist</a>
                        @elseif(in_array($doc['status'], ['submitted', 'pending_verification'], true))Nothing. EventBite is reviewing it.
                        @else Nothing.@endif
                    </td>
                </tr>
            @endforeach
        </x-data-table>
    </section>

    {{-- Upcoming expirations --}}
    <section id="expiring" aria-labelledby="expiring-title" class="eb-stack">
        <div class="eb-section-header"><div>
            <h2 id="expiring-title" class="eb-text-section-title" style="margin: 0;">Upcoming document expirations</h2>
            <p class="eb-text-supporting" style="margin: 0;">Verified documents that have expired or expire in the next {{ \App\Services\Compliance\HostComplianceOverview::EXPIRY_WINDOW_DAYS }} days. An expired document blocks publishing again.</p>
        </div></div>
        <x-data-table caption="Upcoming document expirations" :columns="['Document', 'For', 'Expiry date', 'Status', 'What to do']"
                      :empty="$expiring === []" empty-title="Nothing expiring soon" empty-text="No verified document expires in the next {{ \App\Services\Compliance\HostComplianceOverview::EXPIRY_WINDOW_DAYS }} days.">
            @foreach($expiring as $row)
                <tr>
                    <td style="font-weight: 600;">{{ $row['title'] }}</td>
                    <td>{{ $row['what'] }}</td>
                    <td>{{ $row['date']->format('j M Y') }}</td>
                    <td><span class="eb-badge {{ $badge($row['tone']) }}"><span aria-hidden="true">{{ $icon($row['tone']) }}</span> {{ $row['label'] }}</span></td>
                    <td>{{ $row['action'] }} <a href="{{ $row['url'] }}">Go there</a></td>
                </tr>
            @endforeach
        </x-data-table>
    </section>

    {{-- Review messages --}}
    <section id="messages" aria-labelledby="messages-title" class="eb-stack">
        <div class="eb-section-header"><div>
            <h2 id="messages-title" class="eb-text-section-title" style="margin: 0;">Compliance review messages</h2>
            <p class="eb-text-supporting" style="margin: 0;">Decisions and notes from EventBite's compliance reviewers, newest first.</p>
        </div></div>
        @forelse($messages as $message)
            <article class="eb-card" style="padding: 0.9rem 1.1rem;">
                <div class="eb-cluster" style="justify-content: space-between; gap: 0.5rem;">
                    <strong>{{ $message['subject'] }}</strong>
                    <span class="eb-help-text">{{ $message['from'] }} · {{ $message['at']?->format('j M Y') }}</span>
                </div>
                <p style="margin: 0.35rem 0 0; white-space: pre-line;"><span class="eb-badge {{ $badge($message['tone']) }}" style="margin-right: 0.4rem;"><span aria-hidden="true">{{ $icon($message['tone']) }}</span><span class="sr-only">{{ ucfirst($message['tone']) }}</span></span>{{ $message['body'] }}</p>
            </article>
        @empty
            <x-alert type="info">No review messages yet. When EventBite reviews your ID or a permit, its decision appears here.</x-alert>
        @endforelse
    </section>
</div>
@endsection
