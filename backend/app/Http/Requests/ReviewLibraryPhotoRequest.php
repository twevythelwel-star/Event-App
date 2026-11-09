<?php

declare(strict_types=1);

namespace App\Http\Requests;

use App\Support\AdminAuthorization as Admin;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/**
 * A system administrator other than the uploader reviews a library photo. Approving it means confirming each
 * photography standard (config photo_library.standards); rejecting it needs a reason.
 */
class ReviewLibraryPhotoRequest extends FormRequest
{
    public function authorize(): bool
    {
        return (bool) $this->user()?->can(Admin::PHOTO_LIBRARY_APPROVE, $this->route('photo'));
    }

    public function rules(): array
    {
        $approving = $this->input('decision') === 'approve';
        $rules = [
            'decision' => ['required', Rule::in(['approve', 'reject'])],
            'notes' => [$approving ? 'nullable' : 'required', 'string', 'max:1000'],
        ];
        foreach (array_keys((array) config('photo_library.standards', [])) as $standard) {
            $rules["standards.{$standard}"] = $approving ? ['accepted'] : ['nullable'];
        }

        return $rules;
    }

    public function messages(): array
    {
        $messages = ['notes.required' => 'Say why it is rejected, so the uploader can replace it.'];
        foreach ((array) config('photo_library.standards', []) as $standard => $text) {
            $messages["standards.{$standard}.accepted"] = "Confirm: {$text}.";
        }

        return $messages;
    }
}
