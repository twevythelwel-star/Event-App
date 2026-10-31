@extends('layouts.app')

@section('title', 'Manage Event Staff — ' . $event->title)

@section('content')
<div class="space-y-8 max-w-6xl mx-auto">
    <!-- Header -->
    <div class="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-4 pb-6 border-b border-slate-200">
        <div>
            <div class="flex items-center gap-2 text-xs font-semibold text-slate-500 uppercase tracking-wider mb-1">
                <a href="{{ route('host.dashboard') }}" class="hover:text-indigo-600 transition-colors">Host Dashboard</a>
                <span>&rsaquo;</span>
                <a href="{{ route('events.edit', $event->id) }}" class="hover:text-indigo-600 transition-colors">{{ $event->title }}</a>
                <span>&rsaquo;</span>
                <span class="text-indigo-600">Staff Management</span>
            </div>
            <h1 class="text-2xl font-bold tracking-tight text-slate-900">Manage Event Staff</h1>
            <p class="text-sm text-slate-500 mt-1">Assign verified registered users to specialized positions using privacy-preserving single-use claim codes.</p>
        </div>
        <div class="flex items-center gap-3">
            <a href="{{ route('worker.terminal', $event->id) }}" class="px-4 py-2 text-xs font-semibold text-indigo-700 bg-indigo-50 border border-indigo-200 rounded-lg hover:bg-indigo-100 transition-colors">
                Launch Worker Terminal
            </a>
            <a href="{{ route('host.dashboard') }}" class="px-4 py-2 text-xs font-semibold text-slate-700 bg-white border border-slate-200 rounded-lg hover:bg-slate-50 transition-colors shadow-sm">
                Host Dashboard
            </a>
        </div>
    </div>

    @if (session('status'))
        <div class="p-4 bg-emerald-50 border border-emerald-200 rounded-xl text-emerald-800 text-sm flex items-start gap-2.5">
            <svg class="w-5 h-5 text-emerald-600 flex-shrink-0" fill="currentColor" viewBox="0 0 20 20"><path fill-rule="evenodd" d="M10 18a8 8 0 100-16 8 8 0 000 16zm3.707-9.293a1 1 0 00-1.414-1.414L9 10.586 7.707 9.293a1 1 0 00-1.414 1.414l2 2a1 1 0 001.414 0l4-4z" clip-rule="evenodd"/></svg>
            <span>{{ session('status') }}</span>
        </div>
    @endif

    @if ($errors->any())
        <div class="p-4 bg-rose-50 border border-rose-200 rounded-xl text-rose-800 text-sm space-y-1">
            <div class="font-semibold mb-1">Please fix the following:</div>
            @foreach ($errors->all() as $error)
                <div class="flex items-center gap-2">
                    <svg class="w-4 h-4 text-rose-500 flex-shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8v4m0 4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z"/></svg>
                    <span>{{ $error }}</span>
                </div>
            @endforeach
        </div>
    @endif

    <!-- Assignment Form Card -->
    <div class="bg-white rounded-2xl border border-slate-200 shadow-sm p-6 sm:p-8 space-y-6">
        <div class="flex items-center justify-between pb-4 border-b border-slate-100">
            <div class="flex items-center gap-3">
                <div class="w-10 h-10 rounded-xl bg-indigo-50 text-indigo-600 flex items-center justify-center font-bold">
                    <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M18 9v3m0 0v3m0-3h3m-3 0h-3m-2-5a4 4 0 11-8 0 4 4 0 018 0zM3 20a6 6 0 0112 0v1H3v-1z"/></svg>
                </div>
                <div>
                    <h2 class="text-base font-semibold text-slate-900">Assign Staff Member via Claim Code</h2>
                    <p class="text-xs text-slate-500">Ask the registered account holder to generate a single-use 10-minute code from their account.</p>
                </div>
            </div>
            <span class="inline-flex items-center px-2.5 py-1 rounded-full text-xs font-semibold bg-indigo-50 text-indigo-700 border border-indigo-100">
                Privacy-Preserving
            </span>
        </div>

        <form action="{{ route('host.events.staff.assign', $event->id) }}" method="POST" class="space-y-6">
            @csrf

            <!-- Step 1 & 2: Enter & Validate Code -->
            <div class="grid grid-cols-1 md:grid-cols-2 gap-6 items-start">
                <div class="space-y-2">
                    <label for="claim_code_input" class="block text-xs font-semibold uppercase tracking-wider text-slate-700">Staff Claim Code <span class="text-rose-500">*</span></label>
                    <div class="flex gap-2">
                        <input type="text" id="claim_code_input" name="claim_code" value="{{ old('claim_code') }}" required placeholder="e.g. STF-K7X9-M2W4" class="flex-1 px-3.5 py-2.5 bg-slate-50 border border-slate-200 rounded-xl text-sm font-mono tracking-wider text-slate-900 focus:bg-white focus:outline-none focus:ring-2 focus:ring-indigo-500 focus:border-transparent transition-all uppercase">
                        <button type="button" id="btn_verify_code" class="px-4 py-2.5 text-xs font-semibold text-indigo-600 bg-indigo-50 hover:bg-indigo-100 rounded-xl transition-colors border border-indigo-200 whitespace-nowrap">
                            Verify Code
                        </button>
                    </div>
                    <p class="text-[11px] text-slate-400">Codes are 10-minute expiring tokens. No global user directory or emails are queried.</p>
                </div>

                <!-- Recipient Preview Confirmation -->
                <div id="recipient_preview_box" class="p-4 rounded-xl border border-slate-200 bg-slate-50 min-h-[82px] flex items-center justify-between">
                    <div class="space-y-0.5">
                        <span class="text-[11px] font-semibold text-slate-400 uppercase tracking-wider">Identified Account Holder</span>
                        <div id="preview_name" class="text-sm font-bold text-slate-700">Enter code and click Verify</div>
                        <div id="preview_sub" class="text-xs text-slate-400">Protects attendee privacy by masking personal details.</div>
                    </div>
                    <div id="preview_badge" class="hidden">
                        <span class="inline-flex items-center px-2 py-1 rounded-full text-xs font-semibold bg-emerald-50 text-emerald-700 border border-emerald-200">
                            Verified
                        </span>
                    </div>
                </div>
            </div>

            <!-- Step 3: Position & Schedule -->
            <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-6 pt-4 border-t border-slate-100">
                <div>
                    <label for="worker_role" class="block text-xs font-semibold uppercase tracking-wider text-slate-700 mb-1.5">Assigned Position <span class="text-rose-500">*</span></label>
                    <select id="worker_role" name="worker_role" required class="w-full px-3.5 py-2.5 bg-slate-50 border border-slate-200 rounded-xl text-sm text-slate-900 focus:bg-white focus:outline-none focus:ring-2 focus:ring-indigo-500 focus:border-transparent transition-all">
                        <option value="payment_clerk">Payment Clerk</option>
                        <option value="ticket_checker">Ticket Checker</option>
                        <option value="food_station_worker">Food Station Worker</option>
                        @if (!$isSupervisor)
                            <option value="event_supervisor">Event Supervisor</option>
                        @endif
                        <option value="custom_restricted_worker">Custom Restricted Worker</option>
                    </select>
                </div>

                <div>
                    <label for="station_assignment" class="block text-xs font-semibold uppercase tracking-wider text-slate-700 mb-1.5">Station Scope</label>
                    <select id="station_assignment" name="station_assignment" class="w-full px-3.5 py-2.5 bg-slate-50 border border-slate-200 rounded-xl text-sm text-slate-900 focus:bg-white focus:outline-none focus:ring-2 focus:ring-indigo-500 focus:border-transparent transition-all">
                        <option value="all">All Stations / Venue Wide</option>
                        <option value="mains">Mains Hospitality Tent</option>
                        <option value="sides">Sides & Soups</option>
                        <option value="drinks">Beverages & Bar</option>
                        <option value="gate_a">Gate A (Main Entrance)</option>
                        <option value="gate_b">Gate B (VIP Access)</option>
                    </select>
                </div>

                <div>
                    <label for="starts_at" class="block text-xs font-semibold uppercase tracking-wider text-slate-700 mb-1.5">Shift Starts At <span class="text-rose-500">*</span></label>
                    <input type="datetime-local" id="starts_at" name="starts_at" value="{{ old('starts_at', now()->format('Y-m-d\TH:i')) }}" required class="w-full px-3.5 py-2.5 bg-slate-50 border border-slate-200 rounded-xl text-sm text-slate-900 focus:bg-white focus:outline-none focus:ring-2 focus:ring-indigo-500 focus:border-transparent transition-all">
                </div>

                <div>
                    <label for="expires_at" class="block text-xs font-semibold uppercase tracking-wider text-slate-700 mb-1.5">Shift Expiration <span class="text-rose-500">*</span></label>
                    <input type="datetime-local" id="expires_at" name="expires_at" value="{{ old('expires_at', now()->addHours(12)->format('Y-m-d\TH:i')) }}" required class="w-full px-3.5 py-2.5 bg-slate-50 border border-slate-200 rounded-xl text-sm text-slate-900 focus:bg-white focus:outline-none focus:ring-2 focus:ring-indigo-500 focus:border-transparent transition-all">
                </div>
            </div>

            <div class="grid grid-cols-1 sm:grid-cols-2 gap-6">
                <div>
                    <label for="timezone" class="block text-xs font-semibold uppercase tracking-wider text-slate-700 mb-1.5">Applicable Timezone <span class="text-rose-500">*</span></label>
                    <select id="timezone" name="timezone" class="w-full px-3.5 py-2.5 bg-slate-50 border border-slate-200 rounded-xl text-sm text-slate-900 focus:bg-white focus:outline-none focus:ring-2 focus:ring-indigo-500 focus:border-transparent transition-all">
                        <option value="America/New_York" {{ $event->timezone === 'America/New_York' ? 'selected' : '' }}>America/New_York (EDT / UTC-4)</option>
                        <option value="America/Chicago" {{ $event->timezone === 'America/Chicago' ? 'selected' : '' }}>America/Chicago (CDT / UTC-5)</option>
                        <option value="America/Denver" {{ $event->timezone === 'America/Denver' ? 'selected' : '' }}>America/Denver (Mountain Time)</option>
                        <option value="America/Los_Angeles" {{ $event->timezone === 'America/Los_Angeles' ? 'selected' : '' }}>America/Los_Angeles (PDT / UTC-7)</option>
                        <option value="America/Phoenix" {{ $event->timezone === 'America/Phoenix' ? 'selected' : '' }}>America/Phoenix (Arizona Time)</option>
                        <option value="America/Anchorage" {{ $event->timezone === 'America/Anchorage' ? 'selected' : '' }}>America/Anchorage (Alaska Time)</option>
                        <option value="Pacific/Honolulu" {{ $event->timezone === 'Pacific/Honolulu' ? 'selected' : '' }}>Pacific/Honolulu (Hawaii Time)</option>
                    </select>
                </div>

                <div class="flex items-end">
                    <p class="text-xs text-slate-500 leading-relaxed bg-slate-50 p-3 rounded-xl border border-slate-200 w-full">
                        <strong class="text-slate-700">Strict Expiration Invariant:</strong> At the expiration timestamp, worker terminals immediately revoke authorization even if the user has an active session.
                    </p>
                </div>
            </div>

            <!-- Step 4: Custom Permissions Checklist (Conditional) -->
            <div id="custom_permissions_box" class="hidden space-y-3 pt-4 border-t border-slate-100">
                <span class="block text-xs font-semibold uppercase tracking-wider text-slate-700">Custom Restricted Worker Permissions</span>
                <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-3 gap-3">
                    <label class="flex items-center gap-2 p-2.5 bg-slate-50 border border-slate-200 rounded-xl text-xs text-slate-700 cursor-pointer hover:bg-slate-100">
                        <input type="checkbox" name="custom_permissions[]" value="scan_qr_tickets" class="rounded text-indigo-600 focus:ring-indigo-500">
                        <span>Scan QR Tickets</span>
                    </label>
                    <label class="flex items-center gap-2 p-2.5 bg-slate-50 border border-slate-200 rounded-xl text-xs text-slate-700 cursor-pointer hover:bg-slate-100">
                        <input type="checkbox" name="custom_permissions[]" value="validate_ticket_status" class="rounded text-indigo-600 focus:ring-indigo-500">
                        <span>Validate Ticket Status</span>
                    </label>
                    <label class="flex items-center gap-2 p-2.5 bg-slate-50 border border-slate-200 rounded-xl text-xs text-slate-700 cursor-pointer hover:bg-slate-100">
                        <input type="checkbox" name="custom_permissions[]" value="record_entry" class="rounded text-indigo-600 focus:ring-indigo-500">
                        <span>Record Event Entry</span>
                    </label>
                    <label class="flex items-center gap-2 p-2.5 bg-slate-50 border border-slate-200 rounded-xl text-xs text-slate-700 cursor-pointer hover:bg-slate-100">
                        <input type="checkbox" name="custom_permissions[]" value="view_relevant_orders" class="rounded text-indigo-600 focus:ring-indigo-500">
                        <span>View Assigned Orders</span>
                    </label>
                    <label class="flex items-center gap-2 p-2.5 bg-slate-50 border border-slate-200 rounded-xl text-xs text-slate-700 cursor-pointer hover:bg-slate-100">
                        <input type="checkbox" name="custom_permissions[]" value="record_cash_received" class="rounded text-indigo-600 focus:ring-indigo-500">
                        <span>Record Cash Receipts</span>
                    </label>
                    <label class="flex items-center gap-2 p-2.5 bg-slate-50 border border-slate-200 rounded-xl text-xs text-slate-700 cursor-pointer hover:bg-slate-100">
                        <input type="checkbox" name="custom_permissions[]" value="mark_quantities_collected" class="rounded text-indigo-600 focus:ring-indigo-500">
                        <span>Mark Food Collected</span>
                    </label>
                </div>
            </div>

            <!-- Submit Action -->
            <div class="flex items-center justify-end gap-3 pt-4 border-t border-slate-100">
                <button type="submit" class="px-6 py-2.5 text-sm font-semibold text-white bg-indigo-600 hover:bg-indigo-700 rounded-xl transition-colors shadow-sm shadow-indigo-200">
                    Issue Staff Assignment Offer
                </button>
            </div>
        </form>
    </div>

    <!-- Active Roster & Past Assignments Table -->
    <div class="bg-white rounded-2xl border border-slate-200 shadow-sm overflow-hidden">
        <div class="p-6 border-b border-slate-100 flex flex-col sm:flex-row sm:items-center sm:justify-between gap-4">
            <div>
                <h3 class="text-base font-semibold text-slate-900">Event Staff Roster & Shifts</h3>
                <p class="text-xs text-slate-500">Real-time status of all active, scheduled, pending, and revoked staff assignments.</p>
            </div>
            <span class="text-xs font-semibold text-slate-500">{{ $assignments->count() }} Total Assigned</span>
        </div>

        <div class="overflow-x-auto">
            <table class="w-full text-left border-collapse text-xs">
                <thead>
                    <tr class="bg-slate-50 border-b border-slate-200 text-slate-500 uppercase tracking-wider font-semibold">
                        <th class="py-3 px-4">Staff Member</th>
                        <th class="py-3 px-4">Position</th>
                        <th class="py-3 px-4">Station</th>
                        <th class="py-3 px-4">Shift Schedule</th>
                        <th class="py-3 px-4">Status</th>
                        <th class="py-3 px-4 text-right">Actions</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-slate-100">
                    @forelse ($assignments as $assignment)
                        <tr class="hover:bg-slate-50/50 transition-colors">
                            <td class="py-3 px-4 font-medium text-slate-900">
                                @if ($assignment->user)
                                    <div>{{ $assignment->user->name }}</div>
                                    <div class="text-[11px] text-slate-400 font-mono">{{ $assignment->user->email }}</div>
                                @else
                                    <div class="text-slate-500 italic">Pending Acceptance</div>
                                @endif
                            </td>
                            <td class="py-3 px-4">
                                <span class="inline-flex items-center px-2 py-0.5 rounded-full text-[11px] font-semibold bg-slate-100 text-slate-800 border border-slate-200">
                                    {{ $assignment->worker_role->label() }}
                                </span>
                            </td>
                            <td class="py-3 px-4 text-slate-600 capitalize">
                                {{ str_replace('_', ' ', $assignment->station_assignment ?? 'all') }}
                            </td>
                            <td class="py-3 px-4 text-slate-600">
                                <div>{{ $assignment->starts_at ? $assignment->starts_at->format('M j, g:i A') : 'Immediate' }} &mdash;</div>
                                <div class="text-slate-400">{{ $assignment->expires_at ? $assignment->expires_at->format('M j, g:i A') : 'No Expiry' }} ({{ $assignment->timezone ?? 'UTC' }})</div>
                            </td>
                            <td class="py-3 px-4">
                                @if ($assignment->isRevoked())
                                    <span class="inline-flex items-center px-2 py-0.5 rounded-full text-[11px] font-semibold bg-rose-50 text-rose-700 border border-rose-200">Revoked</span>
                                @elseif ($assignment->isExpired())
                                    <span class="inline-flex items-center px-2 py-0.5 rounded-full text-[11px] font-semibold bg-slate-100 text-slate-600 border border-slate-200">Expired</span>
                                @elseif ($assignment->status === 'pending_acceptance')
                                    <span class="inline-flex items-center px-2 py-0.5 rounded-full text-[11px] font-semibold bg-amber-50 text-amber-700 border border-amber-200">Pending Acceptance</span>
                                @elseif ($assignment->isScheduled())
                                    <span class="inline-flex items-center px-2 py-0.5 rounded-full text-[11px] font-semibold bg-sky-50 text-sky-700 border border-sky-200">Scheduled</span>
                                @elseif ($assignment->isCurrentlyActive())
                                    <span class="inline-flex items-center px-2 py-0.5 rounded-full text-[11px] font-semibold bg-emerald-50 text-emerald-700 border border-emerald-200">Active</span>
                                @else
                                    <span class="inline-flex items-center px-2 py-0.5 rounded-full text-[11px] font-semibold bg-slate-100 text-slate-600 border border-slate-200">{{ ucfirst($assignment->status) }}</span>
                                @endif
                            </td>
                            <td class="py-3 px-4 text-right">
                                @if (!$assignment->isRevoked() && !$assignment->isExpired() && in_array($assignment->status, ['active', 'scheduled', 'pending_acceptance']))
                                    <form action="{{ route('host.events.staff.revoke', [$event->id, $assignment->id]) }}" method="POST" onsubmit="return confirm('Revoke this staff member immediately? Access will terminate immediately.');" class="inline">
                                        @csrf
                                        <button type="submit" class="text-xs font-semibold text-rose-600 hover:text-rose-800 transition-colors">
                                            Revoke
                                        </button>
                                    </form>
                                @else
                                    <span class="text-slate-400">&mdash;</span>
                                @endif
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="6" class="py-8 text-center text-slate-400">
                                No staff members currently assigned to this event.
                            </td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </div>
</div>

<script>
    document.addEventListener('DOMContentLoaded', function () {
        const roleSelect = document.getElementById('worker_role');
        const customBox = document.getElementById('custom_permissions_box');
        const btnVerify = document.getElementById('btn_verify_code');
        const codeInput = document.getElementById('claim_code_input');
        const previewName = document.getElementById('preview_name');
        const previewSub = document.getElementById('preview_sub');
        const previewBadge = document.getElementById('preview_badge');

        roleSelect.addEventListener('change', function () {
            if (this.value === 'custom_restricted_worker') {
                customBox.classList.remove('hidden');
            } else {
                customBox.classList.add('hidden');
            }
        });

        btnVerify.addEventListener('click', async function () {
            const code = codeInput.value.trim();
            if (!code) {
                alert('Please enter a staff claim code first.');
                return;
            }

            btnVerify.disabled = true;
            btnVerify.textContent = 'Verifying...';

            try {
                const response = await fetch("{{ route('host.events.staff.verify-code', $event->id) }}", {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/json',
                        'Accept': 'application/json',
                        'X-CSRF-TOKEN': "{{ csrf_token() }}",
                    },
                    body: JSON.stringify({ claim_code: code }),
                });

                const data = await response.json();

                if (response.ok && data.success) {
                    previewName.textContent = data.masked_name;
                    previewName.classList.remove('text-slate-700', 'text-rose-600');
                    previewName.classList.add('text-emerald-700');
                    previewSub.textContent = 'Account holder verified. You can now select their position and shift schedule.';
                    previewBadge.classList.remove('hidden');
                } else {
                    previewName.textContent = 'Verification Failed';
                    previewName.classList.remove('text-slate-700', 'text-emerald-700');
                    previewName.classList.add('text-rose-600');
                    previewSub.textContent = data.message || 'Unable to verify this code. Ask the account holder to generate a new one.';
                    previewBadge.classList.add('hidden');
                }
            } catch (err) {
                previewName.textContent = 'Verification Error';
                previewName.classList.add('text-rose-600');
                previewSub.textContent = 'Network or server error verifying code.';
            } finally {
                btnVerify.disabled = false;
                btnVerify.textContent = 'Verify Code';
            }
        });
    });
</script>
@endsection
