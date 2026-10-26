<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class UploadFlyerRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            // Max 5120 KB = 5MB, strictly allowed image formats, dimension restrictions
            'flyer' => 'required|file|mimes:jpeg,jpg,png,webp|max:5120|dimensions:min_width=300,min_height=200,max_width=4500,max_height=4500',
        ];
    }

    public function messages(): array
    {
        return [
            'flyer.required' => 'An image file is required for flyer upload.',
            'flyer.mimes' => 'Flyer must be a valid image file (JPG, PNG, or WebP).',
            'flyer.max' => 'Flyer image size cannot exceed 5MB.',
            'flyer.dimensions' => 'Flyer dimensions must be between 300x200 and 4500x4500 pixels.',
        ];
    }
}
