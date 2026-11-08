<?php

declare(strict_types=1);

namespace App\Http\Requests\Compliance;

use App\Models\Compliance\ComplianceSafetyReport;
use App\Models\User;
use App\Support\AdminAuthorization as Admin;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Validator;

/** A compliance administrator updates a safety report. Resolving or dismissing it needs a written finding. */
class UpdateSafetyReportRequest extends FormRequest
{
    public function authorize(): bool
    {
        return (bool) $this->user()?->can('update', $this->route('report'));
    }

    public function rules(): array
    {
        return [
            'status' => ['required', Rule::in(array_keys(ComplianceSafetyReport::STATUSES))],
            'priority' => ['required', Rule::in(array_keys(ComplianceSafetyReport::PRIORITIES))],
            'assigned_to' => ['nullable', 'integer', 'exists:users,id'],
            'resolution' => [Rule::requiredIf(in_array($this->input('status'), ['resolved', 'dismissed'], true)), 'nullable', 'string', 'max:2000'],
        ];
    }

    public function messages(): array
    {
        return ['resolution.required' => 'Say what was found and done before resolving or dismissing a report.'];
    }

    public function after(): array
    {
        return [function (Validator $validator) {
            $assignee = $this->filled('assigned_to') ? User::find($this->input('assigned_to')) : null;
            if ($assignee && !Admin::isComplianceManager($assignee)) {
                $validator->errors()->add('assigned_to', 'Assign it to a compliance administrator.');
            }
        }];
    }
}
