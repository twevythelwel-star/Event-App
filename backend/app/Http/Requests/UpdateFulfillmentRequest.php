<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Facades\Auth;

class UpdateFulfillmentRequest extends FormRequest
{
    public function authorize(): bool
    {
        $user = Auth::user();

        if (!$user) {
            return false;
        }

        $assignment = $this->attributes->get('worker_assignment');

        if ($assignment && $assignment->isCurrentlyActive()) {
            $role = $assignment->worker_role;
            return $role === \App\Enums\EventWorkerRole::FOOD_STATION_WORKER ||
                   $role === \App\Enums\EventWorkerRole::EVENT_SUPERVISOR ||
                   $assignment->canPerform('mark_quantities_collected');
        }

        return $user->hasRole(\App\Enums\AccountType::SYSTEM_ADMIN);
    }

    public function rules(): array
    {
        return [
            'order_id' => ['required', 'string'],
            'order_item_id' => ['required', 'string'],
            'quantity' => ['required', 'integer', 'min:1', 'max:100'],
        ];
    }
}
