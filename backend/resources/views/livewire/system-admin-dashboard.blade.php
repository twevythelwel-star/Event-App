<div>
    <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 1.5rem;">
        <div>
            <h2 style="font-size: 1.5rem; font-weight: 700;">Livewire System Administrator Management</h2>
            <p style="font-size: 0.85rem; color: var(--text-secondary);">Real-time reactive search, user suspension controls, and health monitoring</p>
        </div>
        <div style="display: flex; gap: 0.5rem;">
            <input 
                type="text" 
                wire:model.live.debounce.300ms="searchQuery" 
                placeholder="Search user name or email..."
                style="background: rgba(255,255,255,0.08); border: 1px solid var(--border-color); color: white; padding: 0.5rem 0.75rem; border-radius: 6px; font-size: 0.85rem;"
            >
            <select 
                wire:model.live="selectedStatus"
                style="background: rgba(255,255,255,0.08); border: 1px solid var(--border-color); color: white; padding: 0.5rem 0.75rem; border-radius: 6px; font-size: 0.85rem;"
            >
                <option value="all">All Statuses</option>
                <option value="active">Active Only</option>
                <option value="suspended">Suspended Only</option>
            </select>
        </div>
    </div>

    <!-- Reactive Users Table -->
    <div class="card">
        <table style="width: 100%; border-collapse: collapse; font-size: 0.88rem;">
            <thead>
                <tr style="text-align: left; border-bottom: 1px solid var(--border-color); color: var(--text-secondary);">
                    <th style="padding: 0.6rem;">Name</th>
                    <th style="padding: 0.6rem;">Email</th>
                    <th style="padding: 0.6rem;">Status</th>
                    <th style="padding: 0.6rem; text-align: right;">Action</th>
                </tr>
            </thead>
            <tbody>
                @forelse($users as $user)
                    <tr style="border-bottom: 1px solid rgba(255,255,255,0.04);">
                        <td style="padding: 0.6rem;">{{ $user->name }}</td>
                        <td style="padding: 0.6rem;">{{ $user->email }}</td>
                        <td style="padding: 0.6rem;">
                            <span class="badge {{ $user->status === 'active' ? 'badge-active' : 'badge-suspended' }}">
                                {{ ucfirst($user->status) }}
                            </span>
                        </td>
                        <td style="padding: 0.6rem; text-align: right;">
                            @if(!$user->is_system_admin && $user->id !== Auth::id())
                                <button 
                                    wire:click="toggleUserStatus({{ $user->id }})" 
                                    wire:loading.attr="disabled"
                                    class="btn {{ $user->status === 'active' ? 'btn-danger' : 'btn-primary' }}" 
                                    style="padding: 0.3rem 0.6rem; font-size: 0.75rem;"
                                >
                                    {{ $user->status === 'active' ? 'Suspend' : 'Activate' }}
                                </button>
                            @else
                                <span style="font-size: 0.75rem; color: var(--text-secondary);">Protected</span>
                            @endif
                        </td>
                    </tr>
                @empty
                    <tr>
                        <td colspan="4" style="text-align: center; padding: 1.5rem; color: var(--text-secondary);">
                            No user records match filter criteria.
                        </td>
                    </tr>
                @endforelse
            </tbody>
        </table>
    </div>
</div>
