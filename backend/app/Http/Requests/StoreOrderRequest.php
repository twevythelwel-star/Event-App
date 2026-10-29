<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class StoreOrderRequest extends FormRequest
{
    public function authorize(): bool
    {
        // Public guest orders do not require customer login
        return true;
    }

    public function rules(): array
    {
        return [
            'customer_name' => ['required', 'string', 'min:2', 'max:120'],
            'customer_phone' => ['required', 'string', 'min:7', 'max:30'],
            'email' => ['nullable', 'email', 'max:150'],
            'method' => ['required', 'in:bank,zelle,cashapp,cash,card'],
            'currency' => ['nullable', 'in:USD,JMD'],
            'payment_reference' => ['nullable', 'string', 'max:100'],
            'sender_name' => ['nullable', 'string', 'max:120'],
            'items' => ['required', 'array', 'min:1'],
            'items.*.menu_item_id' => ['required', 'string'],
            'items.*.quantity' => ['required', 'integer', 'min:1', 'max:100'],
            'items.*.selected_choices' => ['nullable', 'array'],
            'items.*.custom_donation_amount' => ['nullable', 'numeric', 'min:0'],
            'items.*.special_instructions' => ['nullable', 'string', 'max:300'],
            'packaging_fee' => ['nullable', 'numeric', 'min:0'],
            'notes' => ['nullable', 'string', 'max:500'],
            'idempotency_key' => ['nullable', 'string', 'max:120'],
        ];
    }
}
