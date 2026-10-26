<?php

namespace App\Livewire;

use App\Enums\AccountType;
use App\Services\ContextSwitchingService;
use Illuminate\Support\Facades\Auth;
use Livewire\Component;

class RoleSwitcher extends Component
{
    public $activeRole;
    public $authorizedRoles = [];

    public function mount(): void
    {
        $user = Auth::user();
        if ($user) {
            $this->activeRole = $user->getActiveRole()->value;
            $this->authorizedRoles = $user->getAuthorizedRoles();
        }
    }

    public function switchRole(string $targetRole): void
    {
        $user = Auth::user();
        if (!$user) {
            return;
        }

        $result = ContextSwitchingService::switchDashboardContext($user, $targetRole, request()->ip());

        if ($result['success']) {
            $this->activeRole = $result['active_role'];
            $this->dispatch('role-context-switched', role: $result['active_role']);
            $this->redirect(route($result['dashboard_route']));
        } else {
            session()->flash('error', $result['message']);
        }
    }

    public function render()
    {
        return view('livewire.role-switcher');
    }
}
