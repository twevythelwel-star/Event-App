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
            'method' => ['required', \Illuminate\Validation\Rule::in(\App\Payments\PaymentMethods::offerable())],
            'currency' => ['nullable', \Illuminate\Validation\Rule::in(\App\Support\Currency::supportedCodes())],
            'payment_reference' => ['nullable', 'string', 'max:100'],
            'sender_name' => ['nullable', 'string', 'max:120'],
            'items' => ['required', 'array', 'min:1'],
            'items.*.menu_item_id' => ['required', 'string'],
            // 0 is allowed: untouched rows on the order form are skipped; the service still requires one line.
            'items.*.quantity' => ['required', 'integer', 'min:0', 'max:100'],
            'items.*.selected_choices' => ['nullable', 'array'],
            'items.*.custom_donation_amount' => ['nullable', 'numeric', 'min:0'],
            'items.*.portion_size' => ['nullable', 'string', 'max:50'],
            'items.*.special_instructions' => ['nullable', 'string', 'max:300'],
            // A meal package's selection (see MealPackage): substitutions, choices and extras.
            'items.*.package' => ['nullable', 'array'],
            'items.*.package.substitutions' => ['nullable', 'array'],
            'items.*.package.choices' => ['nullable', 'array'],
            'items.*.package.add_ons' => ['nullable', 'array'],
            'items.*.package.add_ons.*' => ['integer', 'min:0', 'max:20'],
            // How the food is collected.
            'food' => ['nullable', 'array'],
            'food.method' => ['nullable', 'in:pickup,delivery'],
            'food.pickup_window' => ['nullable', 'string', 'max:11'],
            'food.delivery_location' => ['nullable', 'string', 'max:160'],
            'packaging_fee' => ['nullable', 'numeric', 'min:0'],
            'notes' => ['nullable', 'string', 'max:500'],
            'idempotency_key' => ['nullable', 'string', 'max:120'],
        ];
    }
}
