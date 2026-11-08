<?php

declare(strict_types=1);

namespace App\Http\Requests\Compliance;

use App\Support\AdminAuthorization as Admin;

/** Suspending a host's publishing privileges: a reason, an optional review date, and a confirmation. */
class SuspendHostRequest extends SuspendEventRequest
{
    public function authorize(): bool
    {
        return (bool) $this->user()?->can(Admin::COMPLIANCE_SUSPEND_HOST, $this->route('user'));
    }

    public function rules(): array
    {
        return parent::rules() + [
            'review_by' => ['nullable', 'date', 'after:today'],
            'unpublish' => ['nullable', 'boolean'],
        ];
    }
}
