<?php

namespace App\Livewire;

use App\Enums\AccountType;
use App\Models\SystemPlatformSetting;
use App\Services\AccountSettingsService;
use App\Services\AuditLogService;
use Illuminate\Support\Facades\Auth;
use Livewire\Component;

class AccountSettings extends Component
{
    // Profile
    public $name;
    public $email;
    public $phone;
    public $preferredDisplayCurrency = 'JMD';

    // Password
    public $currentPassword;
    public $newPassword;
    public $confirmPassword;

    // Platform settings (System Admin)
    public $platformName;
    public $adminSessionTimeout = 30;
    public $auditRetentionDays = 365;

    public function mount(): void
    {
        $user = Auth::user();
        if ($user) {
            $this->name = $user->name;
            $this->email = $user->email;
            $this->phone = $user->phone;
            $this->preferredDisplayCurrency = $user->preferred_display_currency ?? 'JMD';

            if ($user->hasRole(AccountType::SYSTEM_ADMIN)) {
                $sys = SystemPlatformSetting::first();
                $this->platformName = $sys->platform_name ?? 'Island Events & Ticketing';
                $this->adminSessionTimeout = $sys->admin_session_timeout_minutes ?? 30;
                $this->auditRetentionDays = $sys->audit_retention_days ?? 365;
            }
        }
    }

    public function saveProfile(): void
    {
        $user = Auth::user();
        if (!$user) return;

        $this->validate([
            'name' => 'required|min:2|max:120',
            'email' => "required|email|unique:users,email,{$user->id}",
            'phone' => 'nullable|min:7',
        ]);

        $user->name = $this->name;
        $user->email = strtolower($this->email);
        $user->phone = $this->phone;
        $user->save();

        session()->flash('profile_success', 'Personal profile saved successfully.');
    }

    public function updateDisplayCurrency(string $currency): void
    {
        $user = Auth::user();
        if (!$user) return;

        AccountSettingsService::updateAttendeeDisplayCurrency($user, $currency);
        $this->preferredDisplayCurrency = $currency;

        session()->flash('currency_success', "Display currency updated to {$currency}. Note: Actual order transaction currency remains strictly governed by event defaults.");
    }

    public function render()
    {
        return view('livewire.account-settings', [
            'activeRole' => Auth::user()?->getActiveRole() ?? AccountType::ATTENDEE,
        ]);
    }
}
