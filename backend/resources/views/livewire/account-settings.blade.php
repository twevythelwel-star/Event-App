<div>
    @if(session('profile_success'))
        <div class="card" style="border-color: rgba(16, 185, 129, 0.4); background: rgba(16, 185, 129, 0.1); color: var(--accent-emerald);">
            ✓ {{ session('profile_success') }}
        </div>
    @endif
    @if(session('currency_success'))
        <div class="card" style="border-color: rgba(59, 130, 246, 0.4); background: rgba(59, 130, 246, 0.1); color: var(--accent-blue);">
            ✓ {{ session('currency_success') }}
        </div>
    @endif

    <div class="card">
        <h3 style="font-size: 1.15rem; font-weight: 700; margin-bottom: 1rem;">Livewire Interactive Profile & Contact</h3>
        <form wire:submit.prevent="saveProfile">
            <div class="grid-2">
                <div>
                    <label style="display: block; font-size: 0.85rem; color: var(--text-secondary); margin-bottom: 0.35rem;">Full Name</label>
                    <input type="text" wire:model="name" style="width: 100%; background: rgba(255,255,255,0.06); border: 1px solid var(--border-color); color: white; padding: 0.55rem; border-radius: 6px;">
                    @error('name') <span style="color: var(--accent-rose); font-size: 0.75rem;">{{ $message }}</span> @enderror
                </div>
                <div>
                    <label style="display: block; font-size: 0.85rem; color: var(--text-secondary); margin-bottom: 0.35rem;">Email Address</label>
                    <input type="email" wire:model="email" style="width: 100%; background: rgba(255,255,255,0.06); border: 1px solid var(--border-color); color: white; padding: 0.55rem; border-radius: 6px;">
                    @error('email') <span style="color: var(--accent-rose); font-size: 0.75rem;">{{ $message }}</span> @enderror
                </div>
            </div>
            <div style="margin-top: 1rem; display: flex; justify-content: flex-end;">
                <button type="submit" class="btn btn-primary" wire:loading.attr="disabled">
                    Save Profile
                </button>
            </div>
        </form>
    </div>

    @if($activeRole === \App\Enums\AccountType::ATTENDEE)
        <div class="card">
            <h3 style="font-size: 1.15rem; font-weight: 700; margin-bottom: 0.5rem;">Attendee Preferred Display Currency</h3>
            <p style="font-size: 0.85rem; color: var(--text-secondary); margin-bottom: 1rem;">
                Toggle your display estimation currency. Invariant: Display currency never alters transaction price or charged currency.
            </p>
            <div style="display: flex; gap: 1rem;">
                <button 
                    type="button" 
                    wire:click="updateDisplayCurrency('JMD')"
                    class="btn {{ $preferredDisplayCurrency === 'JMD' ? 'btn-primary' : 'btn-secondary' }}"
                >
                    JMD (Jamaican Dollar)
                </button>
                <button 
                    type="button" 
                    wire:click="updateDisplayCurrency('USD')"
                    class="btn {{ $preferredDisplayCurrency === 'USD' ? 'btn-primary' : 'btn-secondary' }}"
                >
                    USD (United States Dollar)
                </button>
            </div>
        </div>
    @endif
</div>
