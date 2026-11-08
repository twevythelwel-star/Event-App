<?php

declare(strict_types=1);

namespace App\Http\Requests\Compliance;

use Illuminate\Foundation\Http\FormRequest;

/** Accepting one or more policies, each ticked separately. The controller checks they are ones the person must accept. */
class AcknowledgePoliciesRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user() !== null;
    }

    public function rules(): array
    {
        return ['policies' => ['required', 'array', 'min:1'], 'policies.*' => ['string', 'max:120']];
    }

    public function messages(): array
    {
        return ['policies.required' => 'Tick each policy you have read and accept.'];
    }
}
