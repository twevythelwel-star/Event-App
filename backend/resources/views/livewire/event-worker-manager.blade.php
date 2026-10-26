<div class="space-y-8">
    <!-- Header & Role Matrix Card -->
    <div class="bg-white rounded-xl shadow-sm border border-slate-200 overflow-hidden">
        <div class="p-6 bg-gradient-to-r from-slate-900 to-indigo-950 text-white">
            <div class="flex items-center justify-between">
                <div>
                    <h2 class="text-xl font-bold flex items-center gap-2">
                        <svg class="w-6 h-6 text-amber-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17 20h5v-2a3 3 0 00-5.356-1.857M17 20H7m10 0v-2c0-.656-.126-1.283-.356-1.857M7 20H2v-2a3 3 0 015.356-1.857M7 20v-2c0-.656.126-1.283.356-1.857m0 0a5.002 5.002 0 019.288 0M15 7a3 3 0 11-6 0 3 3 0 016 0zm6 3a2 2 0 11-4 0 2 2 0 014 0zM7 10a2 2 0 11-4 0 2 2 0 014 0z" />
                        </svg>
                        Event-Worker Roles & Operational Responsibilities
                    </h2>
                    <p class="text-slate-300 text-sm mt-1">
                        Delegate on-site operations to specialized staff with strict principle-of-least-privilege boundaries.
                    </p>
                </div>
                <span class="inline-flex items-center px-3 py-1 rounded-full text-xs font-semibold bg-emerald-500/20 text-emerald-300 border border-emerald-500/30">
                    Scoped Least-Privilege
                </span>
            </div>
        </div>

        <!-- 5 Worker Roles Reference Table -->
        <div class="overflow-x-auto">
            <table class="w-full text-left border-collapse">
                <thead>
                    <tr class="bg-slate-50 border-b border-slate-200 text-xs uppercase tracking-wider text-slate-500 font-semibold">
                        <th class="py-3 px-4">Event-worker role</th>
                        <th class="py-3 px-4 text-emerald-700">Can do</th>
                        <th class="py-3 px-4 text-rose-700">Must not be able to do</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-slate-200 text-sm">
                    <!-- Payment Clerk -->
                    <tr class="hover:bg-slate-50/50">
                        <td class="py-3 px-4 font-semibold text-slate-900 whitespace-nowrap flex items-center gap-2">
                            <span class="w-2.5 h-2.5 rounded-full bg-emerald-500"></span>
                            Payment Clerk
                        </td>
                        <td class="py-3 px-4 text-slate-700">
                            View relevant orders, record cash received, review submitted transfer references, and perform permitted payment-verification actions.
                        </td>
                        <td class="py-3 px-4 text-rose-700 font-medium">
                            Change event ownership, change system settings, access unrelated events, or alter financial records without an audit trail.
                        </td>
                    </tr>

                    <!-- Ticket Checker -->
                    <tr class="hover:bg-slate-50/50">
                        <td class="py-3 px-4 font-semibold text-slate-900 whitespace-nowrap flex items-center gap-2">
                            <span class="w-2.5 h-2.5 rounded-full bg-blue-500"></span>
                            Ticket Checker
                        </td>
                        <td class="py-3 px-4 text-slate-700">
                            Scan QR tickets, validate ticket status, and record entry.
                        </td>
                        <td class="py-3 px-4 text-rose-700 font-medium">
                            View unnecessary payment or personal information, issue refunds, or change ticket prices.
                        </td>
                    </tr>

                    <!-- Food Station Worker -->
                    <tr class="hover:bg-slate-50/50">
                        <td class="py-3 px-4 font-semibold text-slate-900 whitespace-nowrap flex items-center gap-2">
                            <span class="w-2.5 h-2.5 rounded-full bg-amber-500"></span>
                            Food Station Worker
                        </td>
                        <td class="py-3 px-4 text-slate-700">
                            View assigned food items and collection instructions, mark quantities collected, and see remaining quantities.
                        </td>
                        <td class="py-3 px-4 text-rose-700 font-medium">
                            View unrelated customer information, change menu prices, or access financial reports.
                        </td>
                    </tr>

                    <!-- Event Supervisor -->
                    <tr class="hover:bg-slate-50/50">
                        <td class="py-3 px-4 font-semibold text-slate-900 whitespace-nowrap flex items-center gap-2">
                            <span class="w-2.5 h-2.5 rounded-full bg-purple-500"></span>
                            Event Supervisor
                        </td>
                        <td class="py-3 px-4 text-slate-700">
                            Oversee assigned event operations, view relevant reports, and manage permitted event-worker assignments.
                        </td>
                        <td class="py-3 px-4 text-rose-700 font-medium">
                            Access other hosts' events or platform-wide administrator settings.
                        </td>
                    </tr>

                    <!-- Custom Restricted Worker -->
                    <tr class="hover:bg-slate-50/50">
                        <td class="py-3 px-4 font-semibold text-slate-900 whitespace-nowrap flex items-center gap-2">
                            <span class="w-2.5 h-2.5 rounded-full bg-slate-500"></span>
                            Custom Restricted Worker
                        </td>
                        <td class="py-3 px-4 text-slate-700">
                            Receive a deliberately selected combination of approved permissions.
                        </td>
                        <td class="py-3 px-4 text-rose-700 font-medium">
                            Gain permissions beyond those explicitly granted.
                        </td>
                    </tr>
                </tbody>
            </table>
        </div>
    </div>

    <!-- Alert / Toast Messages -->
    @if ($feedbackMessage)
        <div class="p-4 rounded-lg bg-emerald-50 border border-emerald-200 text-emerald-800 text-sm flex items-center justify-between">
            <div class="flex items-center gap-2">
                <svg class="w-5 h-5 text-emerald-600" fill="currentColor" viewBox="0 0 20 20">
                    <path fill-rule="evenodd" d="M10 18a8 8 0 100-16 8 8 0 000 16zm3.707-9.293a1 1 0 00-1.414-1.414L9 10.586 7.707 9.293a1 1 0 00-1.414 1.414l2 2a1 1 0 001.414 0l4-4z" clip-rule="evenodd" />
                </svg>
                <span>{{ $feedbackMessage }}</span>
            </div>
            <button wire:click="resetMessages" class="text-emerald-600 hover:text-emerald-900 font-bold">&times;</button>
        </div>
    @endif

    @if ($errorMessage)
        <div class="p-4 rounded-lg bg-rose-50 border border-rose-200 text-rose-800 text-sm flex items-center justify-between">
            <div class="flex items-center gap-2">
                <svg class="w-5 h-5 text-rose-600" fill="currentColor" viewBox="0 0 20 20">
                    <path fill-rule="evenodd" d="M18 10a8 8 0 11-16 0 8 8 0 0116 0zm-7 4a1 1 0 11-2 0 1 1 0 012 0zm-1-9a1 1 0 00-1 1v4a1 1 0 102 0V6a1 1 0 00-1-1z" clip-rule="evenodd" />
                </svg>
                <span>{{ $errorMessage }}</span>
            </div>
            <button wire:click="resetMessages" class="text-rose-600 hover:text-rose-900 font-bold">&times;</button>
        </div>
    @endif

    <!-- Assignment Form Card -->
    <div class="bg-white rounded-xl shadow-sm border border-slate-200 p-6">
        <h3 class="text-lg font-bold text-slate-900 mb-4 flex items-center gap-2">
            <svg class="w-5 h-5 text-indigo-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M18 9v3m0 0v3m0-3h3m-3 0h-3m-2-5a4 4 0 11-8 0 4 4 0 018 0zM3 20a6 6 0 0112 0v1H3v-1z" />
            </svg>
            Assign Worker to Event
        </h3>

        <form wire:submit.prevent="assignWorker" class="space-y-6">
            <div class="grid grid-cols-1 md:grid-cols-3 gap-6">
                <!-- User Select -->
                <div>
                    <label class="block text-sm font-semibold text-slate-700 mb-2">Team Member / User</label>
                    <select wire:model="selectedUserId" class="w-full rounded-lg border-slate-300 focus:border-indigo-500 focus:ring focus:ring-indigo-200 text-sm">
                        <option value="">Select registered user...</option>
                        @foreach ($availableUsers as $user)
                            <option value="{{ $user->id }}">{{ $user->name }} ({{ $user->email }})</option>
                        @endforeach
                    </select>
                    @error('selectedUserId') <p class="text-xs text-rose-600 mt-1">{{ $message }}</p> @enderror
                </div>

                <!-- Role Select -->
                <div>
                    <label class="block text-sm font-semibold text-slate-700 mb-2">Event-Worker Role</label>
                    <select wire:model="workerRole" class="w-full rounded-lg border-slate-300 focus:border-indigo-500 focus:ring focus:ring-indigo-200 text-sm">
                        @foreach ($workerRoles as $role)
                            <option value="{{ $role->value }}">{{ $role->label() }}</option>
                        @endforeach
                    </select>
                    @error('workerRole') <p class="text-xs text-rose-600 mt-1">{{ $message }}</p> @enderror
                </div>

                <!-- Station Assignment -->
                <div>
                    <label class="block text-sm font-semibold text-slate-700 mb-2">Station Assignment</label>
                    <select wire:model="stationAssignment" class="w-full rounded-lg border-slate-300 focus:border-indigo-500 focus:ring focus:ring-indigo-200 text-sm">
                        <option value="all">All Stations / Roaming</option>
                        <option value="mains">Station 1: Mains & Hot Food</option>
                        <option value="sides">Station 2: Sides & Soups</option>
                        <option value="drinks">Station 3: Bar & Beverages</option>
                        <option value="desserts">Station 4: Desserts & Pastries</option>
                        <option value="vip_station">VIP Fast-Track Station</option>
                    </select>
                    @error('stationAssignment') <p class="text-xs text-rose-600 mt-1">{{ $message }}</p> @enderror
                </div>
            </div>

            <!-- Custom Permissions (Visible only when Custom Restricted Worker is selected) -->
            @if ($workerRole === 'custom_restricted_worker')
                <div class="p-4 bg-slate-50 rounded-lg border border-slate-200">
                    <h4 class="text-sm font-bold text-slate-900 mb-2">Approved Permission Selection</h4>
                    <p class="text-xs text-slate-600 mb-4">
                        Custom restricted workers can ONLY perform explicitly checked actions below. They cannot gain permissions beyond those granted.
                    </p>
                    <div class="grid grid-cols-1 sm:grid-cols-2 md:grid-cols-3 gap-3">
                        <label class="flex items-center gap-2 text-xs text-slate-800">
                            <input type="checkbox" wire:model="customPermissions" value="view_relevant_orders" class="rounded border-slate-300 text-indigo-600">
                            View relevant orders
                        </label>
                        <label class="flex items-center gap-2 text-xs text-slate-800">
                            <input type="checkbox" wire:model="customPermissions" value="record_cash_received" class="rounded border-slate-300 text-indigo-600">
                            Record cash received
                        </label>
                        <label class="flex items-center gap-2 text-xs text-slate-800">
                            <input type="checkbox" wire:model="customPermissions" value="review_transfer_references" class="rounded border-slate-300 text-indigo-600">
                            Review transfer references
                        </label>
                        <label class="flex items-center gap-2 text-xs text-slate-800">
                            <input type="checkbox" wire:model="customPermissions" value="verify_payments" class="rounded border-slate-300 text-indigo-600">
                            Verify payments
                        </label>
                        <label class="flex items-center gap-2 text-xs text-slate-800">
                            <input type="checkbox" wire:model="customPermissions" value="scan_qr_tickets" class="rounded border-slate-300 text-indigo-600">
                            Scan QR tickets
                        </label>
                        <label class="flex items-center gap-2 text-xs text-slate-800">
                            <input type="checkbox" wire:model="customPermissions" value="record_entry" class="rounded border-slate-300 text-indigo-600">
                            Record entry
                        </label>
                        <label class="flex items-center gap-2 text-xs text-slate-800">
                            <input type="checkbox" wire:model="customPermissions" value="view_assigned_food_items" class="rounded border-slate-300 text-indigo-600">
                            View assigned food items
                        </label>
                        <label class="flex items-center gap-2 text-xs text-slate-800">
                            <input type="checkbox" wire:model="customPermissions" value="mark_quantities_collected" class="rounded border-slate-300 text-indigo-600">
                            Mark quantities collected
                        </label>
                        <label class="flex items-center gap-2 text-xs text-slate-800">
                            <input type="checkbox" wire:model="customPermissions" value="see_remaining_quantities" class="rounded border-slate-300 text-indigo-600">
                            See remaining quantities
                        </label>
                    </div>
                </div>
            @endif

            <div class="flex justify-end">
                <button type="submit" class="px-5 py-2.5 bg-indigo-600 hover:bg-indigo-700 text-white font-medium rounded-lg text-sm shadow-sm transition">
                    Assign Worker
                </button>
            </div>
        </form>
    </div>

    <!-- Active Worker Roster -->
    <div class="bg-white rounded-xl shadow-sm border border-slate-200 overflow-hidden">
        <div class="p-5 border-b border-slate-200 flex items-center justify-between">
            <h3 class="text-base font-bold text-slate-900">Current Event Worker Roster</h3>
            <span class="text-xs text-slate-500 font-medium">Assigned: {{ $assignments->count() }} workers</span>
        </div>

        <div class="overflow-x-auto">
            <table class="w-full text-left border-collapse">
                <thead>
                    <tr class="bg-slate-50 border-b border-slate-200 text-xs uppercase tracking-wider text-slate-500 font-semibold">
                        <th class="py-3 px-4">Staff Member</th>
                        <th class="py-3 px-4">Worker Role</th>
                        <th class="py-3 px-4">Station</th>
                        <th class="py-3 px-4">Status</th>
                        <th class="py-3 px-4">Assigned By</th>
                        <th class="py-3 px-4 text-right">Actions</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-slate-200 text-sm">
                    @forelse ($assignments as $assignment)
                        <tr class="hover:bg-slate-50">
                            <td class="py-3 px-4">
                                <div class="font-medium text-slate-900">{{ $assignment->user?->name ?? 'Unknown' }}</div>
                                <div class="text-xs text-slate-500">{{ $assignment->user?->email }}</div>
                            </td>
                            <td class="py-3 px-4">
                                <span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-semibold
                                    @if($assignment->worker_role->value === 'payment_clerk') bg-emerald-100 text-emerald-800
                                    @elseif($assignment->worker_role->value === 'ticket_checker') bg-blue-100 text-blue-800
                                    @elseif($assignment->worker_role->value === 'food_station_worker') bg-amber-100 text-amber-800
                                    @elseif($assignment->worker_role->value === 'event_supervisor') bg-purple-100 text-purple-800
                                    @else bg-slate-100 text-slate-800 @endif">
                                    {{ $assignment->worker_role->label() }}
                                </span>
                            </td>
                            <td class="py-3 px-4 text-slate-600 font-mono text-xs uppercase">
                                {{ $assignment->station_assignment }}
                            </td>
                            <td class="py-3 px-4">
                                @if ($assignment->is_active)
                                    <span class="inline-flex items-center gap-1.5 text-xs text-emerald-700 font-medium">
                                        <span class="w-2 h-2 rounded-full bg-emerald-500"></span> Active
                                    </span>
                                @else
                                    <span class="inline-flex items-center gap-1.5 text-xs text-slate-400 font-medium">
                                        <span class="w-2 h-2 rounded-full bg-slate-300"></span> Inactive
                                    </span>
                                @endif
                            </td>
                            <td class="py-3 px-4 text-xs text-slate-500">
                                {{ $assignment->assignedBy?->name ?? 'Host' }}
                            </td>
                            <td class="py-3 px-4 text-right">
                                <button wire:click="toggleWorkerStatus({{ $assignment->id }})" class="text-xs font-semibold text-indigo-600 hover:text-indigo-900">
                                    {{ $assignment->is_active ? 'Deactivate' : 'Activate' }}
                                </button>
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="6" class="py-8 text-center text-slate-400 text-sm">
                                No workers assigned to this event yet. Use the form above to assign specialized staff.
                            </td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </div>
</div>
