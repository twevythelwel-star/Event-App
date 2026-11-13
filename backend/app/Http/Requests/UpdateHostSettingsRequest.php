<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class UpdateHostSettingsRequest extends FormRequest
{
    public function authorize(): bool
    {
        // Which organizer is checked in the controller; here, only hosts may change host settings at all.
        return $this->user()?->hasRole(\App\Enums\AccountType::EVENT_HOST) ?? false;
    }

    public function rules(): array
    {
        return [
            'default_currency' => ['nullable', 'string', \Illuminate\Validation\Rule::in(\App\Support\Currency::supportedCodes())],
            'default_timezone' => ['nullable', 'string', 'timezone'],
            'default_location' => ['nullable', 'string', 'max:255'],
            'default_order_deadline_hours' => ['nullable', 'integer', 'min:1', 'max:168'],
            'default_pickup_instructions' => ['nullable', 'string', 'max:1000'],
            'default_flyer_aspect_ratio' => ['nullable', 'string', 'in:4:5,1:1,16:9'],
            'default_brand_theme' => ['nullable', 'string', 'max:64'],
            'enable_cash' => ['nullable', 'boolean'],
            'enable_zelle' => ['nullable', 'boolean'],
            'enable_cashapp' => ['nullable', 'boolean'],
            ...\App\Models\HostPaymentPolicy::handleRules(),
            'zelle_instructions' => ['nullable', 'string', 'max:500'],
            'cash_app_instructions' => ['nullable', 'string', 'max:255'],
            // Bank transfer is no longer offered.
            'bank_transfer_instructions' => ['prohibited'],
            'require_transfer_reference' => ['nullable', 'boolean'],
            'require_sender_name' => ['nullable', 'boolean'],
            'require_receipt_snapshot' => ['nullable', 'boolean'],
            'partial_payments_enabled' => ['nullable', 'boolean'],
            'deposit_percentage' => ['nullable', 'integer', 'min:10', 'max:90'],
            'refund_policy_deadline_hours' => ['nullable', 'integer', 'min:0', 'max:168'],
            'refund_policy_notes' => ['nullable', 'string', 'max:1000'],
            'refund_policy_mode' => ['nullable', 'in:manual_full_only,manual_full_and_partial,non_refundable'],
            'host_cancellation_policy' => ['nullable', 'in:contact_organizer,manual_refund_if_supported'],
            'postponement_policy' => ['nullable', 'in:tickets_valid_for_new_date,contact_organizer'],
            'weather_cancellation_policy' => ['nullable', 'in:contact_organizer,reschedule_tickets_valid,manual_refund_if_supported'],
            'food_order_cancellation_deadline_hours' => ['nullable', 'integer', 'min:0', 'max:720'],
            'food_order_modification_deadline_hours' => ['nullable', 'integer', 'min:0', 'max:720'],
            'food_prep_buffer_percent' => ['nullable', 'integer', 'min:0', 'max:50'],
        ];
    }

    public function messages(): array
    {
        return \App\Models\HostPaymentPolicy::handleMessages() + [
            'bank_transfer_instructions.prohibited' => 'Bank transfer is no longer offered. Use Zelle, Cash App or cash in person.',
        ];
    }

    /** Turning on Zelle or Cash App needs the handle customers send to. */
    public function withValidator(\Illuminate\Validation\Validator $validator): void
    {
        $validator->after(function (\Illuminate\Validation\Validator $v) {
            foreach (['enable_zelle' => ['zelle_recipient', 'your Zelle email or phone', 'Zelle'], 'enable_cashapp' => ['cash_app_cashtag', 'your $Cashtag', 'Cash App']] as $switch => [$field, $what, $name]) {
                if ($this->boolean($switch) && trim((string) $this->input($field)) === '') {
                    $v->errors()->add($field, "Add {$what} to accept {$name}.");
                }
            }
        });
    }
}
