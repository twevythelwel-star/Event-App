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
        $enforcePolicies = $this->boolean('enforce_policy_acknowledgements');
        $policyRule = $enforcePolicies ? ['required', 'accepted'] : ['sometimes', 'accepted'];
        $emailRule = app()->environment('testing') ? 'email:rfc' : 'email:rfc,dns';

        return [
            'first_name' => ['required', 'string', 'max:100'],
            'last_name' => ['required', 'string', 'max:100'],
            'email' => ['required', 'string', $emailRule, 'max:255', 'unique:users,email'],
            'password' => ['required', 'string', 'min:8', 'confirmed'],
            'password_confirmation' => ['required', 'string'],
            'phone' => ['nullable', 'string', 'max:32'],
            'preferred_display_currency' => ['nullable', 'string', \Illuminate\Validation\Rule::in(\App\Support\Currency::supportedCodes())],
            'agree_terms' => ['accepted'],
            'accept_platform_terms' => $policyRule,
            'accept_food_safety_disclaimer' => $policyRule,
            'accept_alcohol_policy' => $policyRule,
            'accept_host_responsibility' => ['nullable', 'boolean'],
            'enforce_policy_acknowledgements' => ['nullable', 'boolean'],
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

        // When the 3 individual mandatory policy checkboxes are accepted, satisfy agree_terms as well
        if (
            $this->boolean('accept_platform_terms')
            && $this->boolean('accept_food_safety_disclaimer')
            && $this->boolean('accept_alcohol_policy')
            && !$this->has('agree_terms')
        ) {
            $this->merge(['agree_terms' => '1']);
        }
    }
}
