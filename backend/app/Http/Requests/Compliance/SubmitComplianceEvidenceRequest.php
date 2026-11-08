<?php

declare(strict_types=1);

namespace App\Http\Requests\Compliance;

use Illuminate\Foundation\Http\FormRequest;

/**
 * A host submits a permit or licence for one checklist item. The controller checks the item is on the event's
 * checklist; the file goes to private storage.
 */
class SubmitComplianceEvidenceRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user() !== null; // the event itself is resolved through the host's own events (404 otherwise)
    }

    public function rules(): array
    {
        return [
            'item_key' => ['required', 'string', 'max:160'],
            'reference_number' => ['required', 'string', 'max:120'],
            'issuing_authority' => ['required', 'string', 'max:255'],
            'expires_on' => ['nullable', 'date', 'after:today'],
            'note' => ['nullable', 'string', 'max:2000'],
            'document' => ['nullable', 'file', 'max:5120', 'mimes:pdf,jpg,jpeg,png', 'mimetypes:application/pdf,image/jpeg,image/png'],
        ];
    }

    public function messages(): array
    {
        return [
            'expires_on.after' => 'The expiry date must be in the future. An expired permit cannot be accepted.',
            'document.mimes' => 'Upload a PDF, JPEG or PNG file.',
            'document.max' => 'The file must be 5 MB or smaller.',
        ];
    }
}
