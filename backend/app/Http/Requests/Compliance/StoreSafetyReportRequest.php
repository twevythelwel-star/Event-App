<?php

declare(strict_types=1);

namespace App\Http\Requests\Compliance;

use App\Models\Compliance\ComplianceSafetyReport;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/** Someone signed in reports a safety issue at an event. */
class StoreSafetyReportRequest extends FormRequest
{
    public function authorize(): bool
    {
        return (bool) $this->user()?->can('create', ComplianceSafetyReport::class);
    }

    public function rules(): array
    {
        return [
            'category' => ['required', Rule::in(array_keys(ComplianceSafetyReport::CATEGORIES))],
            'description' => ['required', 'string', 'min:20', 'max:5000'],
            'occurred_on' => ['nullable', 'date', 'before_or_equal:today'],
            'may_contact' => ['nullable', 'boolean'],
        ];
    }

    public function messages(): array
    {
        return ['description.min' => 'Describe what happened in at least 20 characters.'];
    }
}
