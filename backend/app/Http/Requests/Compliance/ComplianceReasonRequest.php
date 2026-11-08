<?php

declare(strict_types=1);

namespace App\Http\Requests\Compliance;

use Illuminate\Foundation\Http\FormRequest;

/**
 * A compliance action that needs a written reason: reinstating an event or a host, or revoking an override.
 * The controller authorizes the record itself (its Gate needs the record, resolved there).
 */
class ComplianceReasonRequest extends FormRequest
{
    /** The field holding the reason, and its minimum length. */
    protected string $field = 'reason';
    protected int $min = 10;

    public function authorize(): bool
    {
        return $this->user() !== null;
    }

    public function rules(): array
    {
        return [$this->field => ['required', 'string', 'min:' . $this->min, 'max:2000']];
    }

    public function messages(): array
    {
        return [$this->field . '.min' => "Explain the reason in at least {$this->min} characters. It is kept in the audit log."];
    }
}
