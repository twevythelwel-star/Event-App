<?php

declare(strict_types=1);

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

/**
 * An administrator recording a chargeback the payment provider reported. Authorization (payment review for this
 * order) is checked in the controller once the order is found within the administrator's scope.
 */
class RecordChargebackRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user() !== null;
    }

    public function rules(): array
    {
        return [
            'amount' => ['required', 'numeric', 'min:0.01'],
            'reason' => ['required', 'string', 'min:3', 'max:255'],
            'provider_reference' => ['nullable', 'string', 'max:120'],
            'confirm' => ['accepted'],
        ];
    }

    public function messages(): array
    {
        return ['confirm.accepted' => 'Tick the box to confirm: every ticket on this order stops working at once.'];
    }
}
