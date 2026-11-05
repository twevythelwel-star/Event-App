<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Facades\Auth;

class TicketVerificationRequest extends FormRequest
{
    public function authorize(): bool
    {
        $user = Auth::user();

        if (!$user) {
            return false;
        }

        // Ticket checkers, event supervisors, and system administrators can verify tickets
        $assignment = $this->attributes->get('worker_assignment');

        if ($assignment && $assignment->isCurrentlyActive()) {
            $role = $assignment->worker_role;
            return $role === \App\Enums\EventWorkerRole::TICKET_CHECKER ||
                   $role === \App\Enums\EventWorkerRole::EVENT_SUPERVISOR ||
                   $assignment->canPerform('scan_qr_tickets');
        }

        return $user->hasRole(\App\Enums\AccountType::SYSTEM_ADMIN);
    }

    public function rules(): array
    {
        return [
            'ticket_code' => ['required', 'string', 'max:80'],
            'scan_request_id' => ['nullable', 'uuid'],
        ];
    }
}
