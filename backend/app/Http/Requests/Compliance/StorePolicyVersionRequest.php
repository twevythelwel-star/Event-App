<?php

declare(strict_types=1);

namespace App\Http\Requests\Compliance;

use App\Models\Compliance\CompliancePolicyVersion;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/** Drafting a policy version: a new version of a policy, or a new policy with its own identifier. */
class StorePolicyVersionRequest extends FormRequest
{
    public function authorize(): bool
    {
        return (bool) $this->user()?->can('create', CompliancePolicyVersion::class);
    }

    public function rules(): array
    {
        return [
            'policy_name' => ['nullable', 'string', 'max:120'],
            'identifier' => ['nullable', 'string', 'max:80', 'regex:/^[a-z][a-z0-9_]*$/'],
            'version' => ['required', 'string', 'max:40', 'regex:/^[A-Za-z0-9.\-]+$/'],
            'title' => ['required', 'string', 'max:255'],
            'category' => ['required', Rule::in(array_keys(CompliancePolicyVersion::CATEGORIES))],
            'body' => ['required', 'string', 'min:20', 'max:50000'],
            'change_summary' => ['required', 'string', 'min:10', 'max:2000'],
            'effective_at' => ['nullable', 'date', 'after_or_equal:today'],
            'applicable_roles' => ['required', 'array', 'min:1'],
            'applicable_roles.*' => [Rule::in(array_keys(CompliancePolicyVersion::ROLES))],
            'applicable_jurisdictions' => ['nullable', 'array'],
            'applicable_jurisdictions.*' => ['string', 'regex:/^[A-Za-z]{2}$/'],
            'acknowledgement_mandatory' => ['nullable', 'boolean'],
            'requires_reacknowledgement' => ['nullable', 'boolean'],
        ];
    }

    public function messages(): array
    {
        return [
            'change_summary.required' => 'Say what changed, so the people accepting it know.',
            'applicable_roles.required' => 'Choose who the policy applies to.',
            'identifier.regex' => 'Use lower-case letters, numbers and underscores, starting with a letter.',
        ];
    }
}
