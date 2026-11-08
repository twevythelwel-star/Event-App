<?php

declare(strict_types=1);

namespace App\Http\Requests\Compliance;

/** Suspending an event: a reason the host will read, and a confirmation. */
class SuspendEventRequest extends ComplianceReasonRequest
{
    protected int $min = 20;

    public function rules(): array
    {
        return parent::rules() + ['confirm' => ['accepted']];
    }

    public function messages(): array
    {
        return [
            'reason.min' => 'Explain the compliance reason in at least 20 characters. The host sees it.',
            'confirm.accepted' => 'Tick the box to confirm.',
        ];
    }
}
