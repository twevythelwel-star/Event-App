<?php

declare(strict_types=1);

namespace App\Http\Requests\Compliance;

use App\Services\HostIdentityVerificationService;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/** A host uploads (or replaces) their government ID. The file is scanned, encrypted and kept in private storage. */
class StoreIdentityDocumentRequest extends FormRequest
{
    /** Event hosts and host applicants only (HostIdentityVerificationPolicy::create). */
    public function authorize(): bool
    {
        return (bool) $this->user()?->can('create', \App\Models\HostIdentityVerification::class);
    }

    /** The host application form uses older field names; accept both. */
    protected function prepareForValidation(): void
    {
        if (!$this->filled('document_type') && $this->filled('government_id_type')) {
            $this->merge(['document_type' => $this->input('government_id_type')]);
        }
        if (!$this->filled('document_last4') && $this->filled('government_id_last4')) {
            $this->merge(['document_last4' => $this->input('government_id_last4')]);
        }
    }

    public function rules(): array
    {
        return [
            'document_type' => ['required', 'string', Rule::in(array_keys(HostIdentityVerificationService::supportedDocumentTypes()))],
            'document_type_other_note' => ['nullable', 'string', 'max:255'],
            'legal_full_name' => ['required', 'string', 'min:2', 'max:255'],
            'issuing_country' => ['nullable', 'string', 'max:16'],
            'document_last4' => ['nullable', 'string', 'max:16'],
            'document_expires_at' => ['nullable', 'date'],
            'identity_document' => ['required', 'file'],
            'consent_id_verification' => ['accepted'],
        ];
    }

    public function messages(): array
    {
        return ['consent_id_verification.accepted' => 'You must consent to secure identity verification processing and the document retention policy.'];
    }
}
