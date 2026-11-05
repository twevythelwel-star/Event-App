<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Facades\Auth;

class CreateWorkerAssignmentRequest extends FormRequest
{
    public function authorize(): bool
    {
        $user = Auth::user();

        if (!$user) {
            return false;
        }

        // Only system administrators, delegated administrators, and event hosts can create assignments
        return $user->hasRole(\App\Enums\AccountType::SYSTEM_ADMIN) ||
               $user->hasRole(\App\Enums\AccountType::ADMINISTRATOR) ||
               $user->hasRole(\App\Enums\AccountType::EVENT_HOST);
    }

    public function rules(): array
    {
        return [
            'event_id' => ['required', 'uuid', 'exists:events,id'],
            'worker_role' => ['required', 'string', 'in:payment_clerk,ticket_checker,food_station_worker,event_supervisor,custom_restricted_worker'],
            'station_assignment' => ['nullable', 'string', 'max:64'],
            'custom_permissions' => ['nullable', 'array'],
            'custom_permissions.*' => ['string'],
            'starts_at' => ['required', 'date'],
            'expires_at' => ['required', 'date', 'after:starts_at'],
            'timezone' => ['required', 'string', 'timezone'],
            'user_id' => ['nullable', 'uuid', 'exists:users,id'],
            'invite_email' => ['nullable', 'email', 'max:255'],
        ];
    }
}
