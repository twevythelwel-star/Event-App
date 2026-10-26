<?php

namespace App\Http\Requests;

use App\Enums\AccountType;
use Illuminate\Foundation\Http\FormRequest;

class UpdatePlatformSettingsRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user() !== null && $this->user()->hasRole(AccountType::SYSTEM_ADMIN);
    }

    public function rules(): array
    {
        return [
            'platform_name' => ['required', 'string', 'min:3', 'max:120'],
            'logo_url' => ['nullable', 'string', 'max:255'],
            'brand_theme' => ['nullable', 'string', 'max:64'],
            'supported_currencies' => ['required', 'array', 'min:1'],
            'supported_currencies.*' => ['string', 'in:JMD,USD'],
            'supported_payment_methods' => ['required', 'array', 'min:1'],
            'supported_payment_methods.*' => ['string', 'in:cash,bank,zelle,cashapp,card'],
            'mandatory_2fa_for_admins' => ['nullable', 'boolean'],
            'admin_session_timeout_minutes' => ['nullable', 'integer', 'min:5', 'max:1440'],
            'attendee_session_timeout_days' => ['nullable', 'integer', 'min:1', 'max:90'],
            'default_timezone' => ['nullable', 'string', 'timezone'],
            'audit_retention_days' => ['nullable', 'integer', 'min:30', 'max:3650'],
            'pii_scrub_days' => ['nullable', 'integer', 'min:7', 'max:365'],
        ];
    }
}
