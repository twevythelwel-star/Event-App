<?php

declare(strict_types=1);

namespace App\Http\Requests;

use App\Services\SystemAdminProvisioningService;
use Illuminate\Foundation\Http\FormRequest;

class RegisterRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'first_name' => ['required', 'string', 'max:100'],
            'last_name' => ['required', 'string', 'max:100'],
            'email' => ['required', 'string', 'email:rfc,dns', 'max:255', 'unique:users,email'],
            'password' => ['required', 'string', 'min:8', 'confirmed'],
            'phone' => ['nullable', 'string', 'max:32'],
            'preferred_display_currency' => ['nullable', 'string', 'in:JMD,USD'],
            'agree_terms' => ['accepted'],
        ];
    }

    /**
     * Prepare data for validation & ensure anti-escalation safety.
     */
    protected function prepareForValidation(): void
    {
        // Enforce anti-escalation security invariant before processing
        SystemAdminProvisioningService::assertPublicRegistrationSafe($this->all());

        if ($this->has('email')) {
            $this->merge([
                'email' => strtolower(trim((string) $this->input('email'))),
            ]);
        }
    }
}
