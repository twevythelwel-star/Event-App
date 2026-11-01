@extends('layouts.app')

@section('title', 'Manage Event Staff — ' . $event->title)
@section('page-title', 'Event staff')

@php
    // Food stations are matched against a menu item's category, so the choices are this event's own categories.
    $stations = \App\Models\MenuItem::withoutGlobalScopes()->where('event_id', $event->id)
        ->whereNotNull('category')->distinct()->orderBy('category')->pluck('category');
    $timezones = ['America/New_York' => 'Eastern', 'America/Chicago' => 'Central', 'America/Denver' => 'Mountain',
        'America/Phoenix' => 'Arizona', 'America/Los_Angeles' => 'Pacific', 'America/Anchorage' => 'Alaska', 'Pacific/Honolulu' => 'Hawaii'];
    $customPermissions = [
        'scan_qr_tickets' => 'Scan QR tickets',
        'validate_ticket_status' => 'Check ticket status',
        'record_entry' => 'Record event entry',
        'view_relevant_orders' => 'View assigned orders',
        'record_cash_received' => 'Record cash received',
        'mark_quantities_collected' => 'Mark food collected',
    ];
@endphp

@section('content')
<div class="eb-stack" style="gap: var(--layout-section-gap);">
    <x-page-header
        title="Manage event staff"
        description="Add registered people to this event with a single-use claim code from their account, set their position and shift, and revoke access at any time."
        :breadcrumbs="$isSupervisor
            ? ['My tickets & orders' => route('attendee.dashboard'), $event->title => null]
            : ['Host dashboard' => route('host.dashboard'), $event->title => route('events.edit', $event->id), 'Staff' => null]">
        <x-slot:actions>
            <x-button variant="secondary" size="sm" icon="checkin" :href="route('worker.terminal', $event->id)">Open terminal</x-button>
        </x-slot:actions>
    </x-page-header>

    <x-card title="Assign a staff member" subtitle="Ask the person to generate a staff claim code from their account. Codes expire after 10 minutes and are used once.">
        <form action="{{ route('host.events.staff.assign', $event->id) }}" method="POST" class="eb-stack" style="gap: var(--layout-form-gap);">
            @csrf

            <div class="eb-grid-2">
                <div class="eb-form-group">
                    <label for="claim_code_input" class="eb-label">Staff claim code <span class="eb-label-required" aria-hidden="true">*</span></label>
                    <div class="eb-cluster" style="flex-wrap: nowrap;">
                        <input type="text" id="claim_code_input" name="claim_code" value="{{ old('claim_code') }}" required
                               autocomplete="off" spellcheck="false" placeholder="e.g. STF-K7X9-M2W4"
                               class="eb-input @error('claim_code') eb-input-error @enderror" style="text-transform: uppercase; font-family: var(--font-mono); flex: 1;">
                        <button type="button" id="btn_verify_code" class="eb-btn eb-btn-secondary">Verify code</button>
                    </div>
                    @error('claim_code')<p class="eb-field-error">{{ $message }}</p>@enderror
                    <p class="eb-help-text">Only the code is checked; no directory of users or emails is searched.</p>
                </div>

                <div class="eb-panel" id="recipient_preview_box" aria-live="polite" style="margin: 0;">
                    <span class="eb-text-label">Account holder</span>
                    <div id="preview_name" class="eb-text-card-title" style="margin-top: var(--space-1);">Enter a code and choose Verify</div>
                    <div id="preview_sub" class="eb-help-text">Their name is shown masked to protect their privacy.</div>
                </div>
            </div>

            <div class="eb-grid-4">
                <div class="eb-form-group">
                    <label for="worker_role" class="eb-label">Position <span class="eb-label-required" aria-hidden="true">*</span></label>
                    <select id="worker_role" name="worker_role" required class="eb-select">
                        @foreach(\App\Enums\EventWorkerRole::cases() as $role)
                            @continue($isSupervisor && $role === \App\Enums\EventWorkerRole::EVENT_SUPERVISOR)
                            <option value="{{ $role->value }}" @selected(old('worker_role') === $role->value)>{{ $role->label() }}</option>
                        @endforeach
                    </select>
                </div>
                <div class="eb-form-group">
                    <label for="station_assignment" class="eb-label">Food station</label>
                    <select id="station_assignment" name="station_assignment" class="eb-select">
                        <option value="all">All stations</option>
                        @foreach($stations as $station)
                            <option value="{{ $station }}" @selected(old('station_assignment') === $station)>{{ ucfirst(str_replace('_', ' ', $station)) }}</option>
                        @endforeach
                    </select>
                    <p class="eb-help-text">Food station workers only hand over items from this menu category.</p>
                </div>
                <div class="eb-form-group">
                    <label for="starts_at" class="eb-label">Shift starts <span class="eb-label-required" aria-hidden="true">*</span></label>
                    <input type="datetime-local" id="starts_at" name="starts_at" required class="eb-input @error('starts_at') eb-input-error @enderror"
                           value="{{ old('starts_at', now($event->timezone ?: 'America/New_York')->format('Y-m-d\TH:i')) }}">
                    @error('starts_at')<p class="eb-field-error">{{ $message }}</p>@enderror
                </div>
                <div class="eb-form-group">
                    <label for="expires_at" class="eb-label">Shift ends <span class="eb-label-required" aria-hidden="true">*</span></label>
                    <input type="datetime-local" id="expires_at" name="expires_at" required class="eb-input @error('expires_at') eb-input-error @enderror"
                           value="{{ old('expires_at', now($event->timezone ?: 'America/New_York')->addHours(12)->format('Y-m-d\TH:i')) }}">
                    @error('expires_at')<p class="eb-field-error">{{ $message }}</p>@enderror
                </div>
            </div>

            <div class="eb-form-group" style="max-width: 22rem;">
                <label for="timezone" class="eb-label">Time zone of the shift times <span class="eb-label-required" aria-hidden="true">*</span></label>
                <select id="timezone" name="timezone" class="eb-select">
                    @foreach($timezones as $tz => $label)
                        <option value="{{ $tz }}" @selected(old('timezone', $event->timezone ?: 'America/New_York') === $tz)>{{ $label }} ({{ $tz }})</option>
                    @endforeach
                </select>
                <p class="eb-help-text">Access starts and stops at these times, even for someone already signed in.</p>
            </div>

            <fieldset id="custom_permissions_box" hidden style="border: 0; padding: 0; margin: 0;">
                <legend class="eb-label" style="margin-bottom: var(--space-2);">What this custom worker may do</legend>
                <div class="eb-grid-3">
                    @foreach($customPermissions as $value => $label)
                        <label class="eb-choice-label">
                            <input type="checkbox" class="eb-checkbox" name="custom_permissions[]" value="{{ $value }}" @checked(in_array($value, old('custom_permissions', []), true))>
                            <span>{{ $label }}</span>
                        </label>
                    @endforeach
                </div>
            </fieldset>

            <div class="eb-cluster" style="justify-content: flex-end;">
                <x-button type="submit" variant="primary">Send assignment offer</x-button>
            </div>
        </form>
    </x-card>

    <x-card :title="'Staff on this event (' . $assignments->count() . ')'" subtitle="Everyone offered a position, with their shift and current status.">
        <x-data-table caption="Event staff" :columns="['Staff member', 'Position', 'Station', 'Shift', 'Status', ['label' => 'Actions', 'numeric' => true]]"
                      :empty="$assignments->isEmpty()" empty-title="No staff yet" empty-text="Staff you assign appear here once you send an offer.">
            @foreach($assignments as $assignment)
                @php
                    [$statusLabel, $statusClass] = match (true) {
                        $assignment->isRevoked() => ['Revoked', 'eb-badge-error'],
                        $assignment->isExpired() => ['Expired', ''],
                        $assignment->status === 'pending_acceptance' => ['Awaiting acceptance', 'eb-badge-warning'],
                        $assignment->isScheduled() => ['Scheduled', 'eb-badge-info'],
                        $assignment->isCurrentlyActive() => ['Active', 'eb-badge-success'],
                        default => [ucfirst(str_replace('_', ' ', (string) $assignment->status)), ''],
                    };
                    $tz = $assignment->timezone ?: ($event->timezone ?: 'UTC');
                @endphp
                <tr>
                    <td>
                        @if($assignment->user)
                            <div style="font-weight: 600;">{{ $assignment->user->name }}</div>
                            <div class="eb-help-text">{{ $assignment->user->email }}</div>
                        @else
                            <span class="eb-help-text">Awaiting acceptance</span>
                        @endif
                    </td>
                    <td>{{ $assignment->worker_role->label() }}</td>
                    <td>{{ ($assignment->station_assignment ?? 'all') === 'all' ? 'All stations' : ucfirst(str_replace('_', ' ', $assignment->station_assignment)) }}</td>
                    <td>
                        <div>{{ $assignment->starts_at ? $assignment->starts_at->copy()->setTimezone($tz)->format('M j, g:i A') : 'Immediately' }}</div>
                        <div class="eb-help-text">to {{ $assignment->expires_at ? $assignment->expires_at->copy()->setTimezone($tz)->format('M j, g:i A T') : 'no end set' }}</div>
                    </td>
                    <td><span class="eb-badge {{ $statusClass }}">{{ $statusLabel }}</span></td>
                    <td class="eb-table-num">
                        @if(!$assignment->isRevoked() && !$assignment->isExpired() && in_array($assignment->status, ['active', 'scheduled', 'pending_acceptance'], true))
                            <form action="{{ route('host.events.staff.revoke', [$event->id, $assignment->id]) }}" method="POST"
                                  onsubmit="return confirm('Revoke this person\'s access now? They lose access immediately.');" style="display: inline;">
                                @csrf
                                <button type="submit" class="eb-btn eb-btn-destructive eb-btn-sm">Revoke</button>
                            </form>
                        @else
                            <span class="eb-help-text">—</span>
                        @endif
                    </td>
                </tr>
            @endforeach
        </x-data-table>
    </x-card>
</div>

<script>
    document.addEventListener('DOMContentLoaded', function () {
        const roleSelect = document.getElementById('worker_role');
        const customBox = document.getElementById('custom_permissions_box');
        const btnVerify = document.getElementById('btn_verify_code');
        const codeInput = document.getElementById('claim_code_input');
        const previewName = document.getElementById('preview_name');
        const previewSub = document.getElementById('preview_sub');

        const syncCustom = () => { customBox.hidden = roleSelect.value !== 'custom_restricted_worker'; };
        roleSelect.addEventListener('change', syncCustom);
        syncCustom();

        btnVerify.addEventListener('click', async function () {
            const code = codeInput.value.trim();
            if (!code) {
                previewName.textContent = 'Enter a claim code first';
                codeInput.focus();
                return;
            }
            btnVerify.disabled = true;
            btnVerify.textContent = 'Verifying…';
            try {
                const response = await fetch(@json(route('host.events.staff.verify-code', $event->id)), {
                    method: 'POST',
                    headers: { 'Content-Type': 'application/json', 'Accept': 'application/json', 'X-CSRF-TOKEN': @json(csrf_token()) },
                    body: JSON.stringify({ claim_code: code }),
                });
                const data = await response.json();
                if (response.ok && data.success) {
                    previewName.textContent = data.masked_name;
                    previewName.style.color = 'var(--color-success-text)';
                    previewSub.textContent = 'Verified. Choose their position and shift, then send the offer.';
                } else {
                    previewName.textContent = 'Code not accepted';
                    previewName.style.color = 'var(--color-error-text)';
                    previewSub.textContent = data.message || 'Ask the account holder to generate a new code.';
                }
            } catch (err) {
                previewName.textContent = 'Could not check the code';
                previewName.style.color = 'var(--color-error-text)';
                previewSub.textContent = 'Check your connection and try again.';
            } finally {
                btnVerify.disabled = false;
                btnVerify.textContent = 'Verify code';
            }
        });
    });
</script>
@endsection
