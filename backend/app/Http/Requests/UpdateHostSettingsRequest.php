<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class UpdateHostSettingsRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user() !== null && $this->user()->canOperateOwnEvents();
    }

    public function rules(): array
    {
        return [
            'default_currency' => ['nullable', 'string', 'in:JMD,USD'],
            'default_timezone' => ['nullable', 'string', 'timezone'],
            'default_location' => ['nullable', 'string', 'max:255'],
            'default_order_deadline_hours' => ['nullable', 'integer', 'min:1', 'max:168'],
            'default_pickup_instructions' => ['nullable', 'string', 'max:1000'],
            'default_flyer_aspect_ratio' => ['nullable', 'string', 'in:4:5,1:1,16:9'],
            'default_brand_theme' => ['nullable', 'string', 'max:64'],
            'enable_cash' => ['nullable', 'boolean'],
            'zelle_instructions' => ['nullable', 'string', 'max:500'],
            'cash_app_instructions' => ['nullable', 'string', 'max:255'],
            'bank_transfer_instructions' => ['nullable', 'string', 'max:1000'],
            'require_transfer_reference' => ['nullable', 'boolean'],
            'require_sender_name' => ['nullable', 'boolean'],
            'require_receipt_snapshot' => ['nullable', 'boolean'],
            'partial_payments_enabled' => ['nullable', 'boolean'],
            'deposit_percentage' => ['nullable', 'integer', 'min:10', 'max:90'],
            'refund_policy_deadline_hours' => ['nullable', 'integer', 'min:0', 'max:168'],
            'refund_policy_notes' => ['nullable', 'string', 'max:1000'],
        ];
    }
}
