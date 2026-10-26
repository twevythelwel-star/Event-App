<?php

namespace App\Livewire;

use App\Enums\AccountType;
use App\Models\Event;
use App\Models\Order;
use App\Models\SecurityAuditLog;
use App\Models\SystemPlatformSetting;
use App\Models\User;
use App\Models\UserRole;
use App\Services\AuditLogService;
use Illuminate\Support\Facades\Auth;
use Livewire\Component;

class SystemAdminDashboard extends Component
{
    public $searchQuery = '';
    public $selectedStatus = 'all';
    public $showLogsModal = false;

    public function mount(): void
    {
        // Enforce authorization
        $user = Auth::user();
        if (!$user || !$user->hasRole(AccountType::SYSTEM_ADMIN)) {
            abort(403, 'Unauthorized access to System Administrator dashboard.');
        }
    }

    public function toggleUserStatus(int $userId): void
    {
        $targetUser = User::findOrFail($userId);

        if ($targetUser->is_system_admin || $targetUser->id === Auth::id()) {
            session()->flash('error', 'Cannot suspend a root System Administrator account.');
            return;
        }

        $newStatus = $targetUser->status === 'active' ? 'suspended' : 'active';
        $targetUser->status = $newStatus;
        $targetUser->save();

        AuditLogService::log(
            action: 'livewire_user_status_toggled',
            targetEntity: 'users',
            targetId: (string) $targetUser->id,
            details: "System admin toggled status of {$targetUser->email} to {$newStatus}.",
            actor: Auth::user()
        );

        session()->flash('success', "User {$targetUser->name} account is now {$newStatus}.");
    }

    public function render()
    {
        $query = User::query();

        if ($this->searchQuery) {
            $query->where(function ($q) {
                $q->where('name', 'like', "%{$this->searchQuery}%")
                  ->orWhere('email', 'like', "%{$this->searchQuery}%");
            });
        }

        if ($this->selectedStatus !== 'all') {
            $query->where('status', $this->selectedStatus);
        }

        $users = $query->orderBy('name')->take(20)->get();
        $auditLogs = SecurityAuditLog::orderByDesc('created_at')->take(15)->get();
        $platform = SystemPlatformSetting::first();

        return view('livewire.system-admin-dashboard', [
            'users' => $users,
            'auditLogs' => $auditLogs,
            'platform' => $platform,
            'totalUsersCount' => User::count(),
            'totalOrdersCount' => Order::count(),
            'totalEventsCount' => Event::count(),
        ]);
    }
}
