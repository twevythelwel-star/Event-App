<?php

declare(strict_types=1);

namespace App\Http\Requests\Compliance;

use App\Models\EventComplianceEvidence;
use Illuminate\Foundation\Http\FormRequest;

/** An administrator starts verifying, verifies or rejects a permit or licence. A rejection needs a reason. */
class ReviewComplianceEvidenceRequest extends FormRequest
{
    public function authorize(): bool
    {
        return (bool) $this->user()?->can('viewAny', EventComplianceEvidence::class);
    }

    public function rules(): array
    {
        return ['comment' => [$this->route('action') === 'reject' ? 'required' : 'nullable', 'string', 'max:2000']];
    }

    public function messages(): array
    {
        return ['comment.required' => 'Give the reason for rejecting it. The host sees it.'];
    }
}
