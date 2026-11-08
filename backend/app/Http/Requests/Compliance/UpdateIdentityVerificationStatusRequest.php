<?php

declare(strict_types=1);

namespace App\Http\Requests\Compliance;

use App\Enums\HostVerificationStatus;
use App\Models\HostIdentityVerification;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/** A decision on a host's government ID. Only administrators granted identity document access may make one. */
class UpdateIdentityVerificationStatusRequest extends FormRequest
{
    public function authorize(): bool
    {
        return (bool) $this->user()?->can('viewAny', HostIdentityVerification::class);
    }

    public function rules(): array
    {
        return [
            'status' => ['required', 'string', Rule::in(HostVerificationStatus::values())],
            'reason' => ['nullable', 'string', 'max:1000'],
            'notes' => ['nullable', 'string', 'max:1000'],
        ];
    }
}
