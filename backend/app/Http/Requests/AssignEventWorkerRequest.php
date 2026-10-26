<?php

declare(strict_types=1);

namespace App\Http\Requests;

use App\Enums\EventWorkerRole;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class AssignEventWorkerRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user() !== null;
    }

    public function rules(): array
    {
        return [
            'user_id' => ['required', 'integer', 'exists:users,id'],
            'worker_role' => ['required', 'string', Rule::enum(EventWorkerRole::class)],
            'station_assignment' => ['nullable', 'string', 'max:50'],
            'custom_permissions' => ['nullable', 'array'],
            'custom_permissions.*' => [
                'string',
                Rule::in([
                    'view_relevant_orders',
                    'record_cash_received',
                    'review_transfer_references',
                    'verify_payments',
                    'scan_qr_tickets',
                    'validate_ticket_status',
                    'record_entry',
                    'view_assigned_food_items',
                    'view_collection_instructions',
                    'mark_quantities_collected',
                    'see_remaining_quantities',
                ]),
            ],
        ];
    }
}
