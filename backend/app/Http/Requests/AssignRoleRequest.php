<?php

namespace App\Http\Requests;

use App\Enums\AccountType;
use Illuminate\Foundation\Http\FormRequest;

class AssignRoleRequest extends FormRequest
{
    public function authorize(): bool
    {
        $user = $this->user();
        if (!$user || !$user->isActive()) {
            return false;
        }

        $targetUserId = (int) $this->route('target_user_id', $this->input('target_user_id'));
        $roleToAssign = $this->input('role');

        // Cannot assign roles to yourself
        if ($user->id === $targetUserId) {
            return false;
        }

        // Ordinary Admins cannot promote anyone to system_admin
        if ($roleToAssign === AccountType::SYSTEM_ADMIN->value && !$user->hasRole(AccountType::SYSTEM_ADMIN)) {
            return false;
        }

        return $user->hasRole(AccountType::SYSTEM_ADMIN) || $user->hasRole(AccountType::ADMINISTRATOR);
    }

    public function rules(): array
    {
        return [
            'target_user_id' => ['required', 'exists:users,id'],
            'role' => ['required', 'string', 'in:system_admin,admin,event_host,attendee'],
        ];
    }
}
