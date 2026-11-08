<?php

declare(strict_types=1);

namespace App\Http\Requests\Compliance;

use App\Http\Controllers\Admin\CompliancePublicationOverrideController;
use App\Support\AdminAuthorization as Admin;
use Illuminate\Foundation\Http\FormRequest;

/** Overriding one requirement blocking an event: explicit permission, a documented reason and a review date. */
class StorePublicationOverrideRequest extends FormRequest
{
    public function authorize(): bool
    {
        return (bool) $this->user()?->can(Admin::COMPLIANCE_OVERRIDE);
    }

    public function rules(): array
    {
        return [
            'gate_code' => ['required', 'string', 'max:200'],
            'reason' => ['required', 'string', 'min:20', 'max:2000'],
            'review_by' => ['required', 'date', 'after:today', 'before_or_equal:' . now()->addDays(CompliancePublicationOverrideController::MAX_DAYS)->toDateString()],
            'confirm' => ['accepted'],
        ];
    }

    public function messages(): array
    {
        return [
            'reason.min' => 'Document the reason in at least 20 characters: what was checked and why the event may proceed.',
            'review_by.before_or_equal' => 'An override lasts at most ' . CompliancePublicationOverrideController::MAX_DAYS . ' days; set an earlier review date.',
            'confirm.accepted' => 'Confirm that you have authority to override this requirement.',
        ];
    }
}
