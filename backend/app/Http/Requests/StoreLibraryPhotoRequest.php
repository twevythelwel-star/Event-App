<?php

declare(strict_types=1);

namespace App\Http\Requests;

use App\Models\LibraryPhoto;
use App\Services\PhotoLibrary;
use App\Support\AdminAuthorization as Admin;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/**
 * Adding a photo to the event photography library: the file, what kind of event it shows, its alt text and focal
 * point, and where it came from (source, licence, consent for people shown). It waits for a second administrator's
 * review before it can appear anywhere.
 */
class StoreLibraryPhotoRequest extends FormRequest
{
    public function authorize(): bool
    {
        return (bool) $this->user()?->can(Admin::PHOTO_LIBRARY_MANAGE);
    }

    public function rules(): array
    {
        return [
            'photo' => ['required', 'file', 'max:5120', 'mimes:jpg,jpeg,png,webp', 'mimetypes:image/jpeg,image/png,image/webp'],
            'subject' => ['required', Rule::in(array_keys(PhotoLibrary::subjects()))],
            'title' => ['required', 'string', 'max:160'],
            'alt_text' => ['required', 'string', 'min:15', 'max:300'],
            'focal_x' => ['nullable', 'integer', 'between:0,100'],
            'focal_y' => ['nullable', 'integer', 'between:0,100'],
            'source' => ['required', Rule::in(array_keys(LibraryPhoto::SOURCES))],
            'source_detail' => ['required', 'string', 'max:255'],
            'source_url' => ['nullable', 'url', 'max:500'],
            'licence' => ['required', 'string', 'max:160'],
            'licence_url' => ['nullable', 'url', 'max:500'],
            'identifiable_people' => ['nullable', 'boolean'],
            'model_release' => ['required', Rule::in(array_keys(LibraryPhoto::MODEL_RELEASES))],
            'rights_confirmed' => ['accepted'],
        ];
    }

    public function messages(): array
    {
        return [
            'alt_text.min' => 'Describe what the photo shows in at least 15 characters, for people using screen readers.',
            'source_detail.required' => 'Say where it came from: the generator, the photographer or the stock library.',
            'rights_confirmed.accepted' => 'Confirm EventBite may use this photo commercially.',
            'photo.max' => 'The photo must be 5 MB or smaller.',
        ];
    }
}
