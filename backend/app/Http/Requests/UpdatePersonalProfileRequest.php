<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class UpdatePersonalProfileRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user() !== null && $this->user()->isActive();
    }

    public function rules(): array
    {
        $userId = $this->user()?->id;

        return [
            'name' => ['required', 'string', 'min:2', 'max:120'],
            'email' => ['required', 'email', 'max:150', "unique:users,email,{$userId}"],
            'phone' => ['nullable', 'string', 'min:7', 'max:32'],
            'preferred_display_currency' => ['nullable', 'string', \Illuminate\Validation\Rule::in(\App\Support\Currency::supportedCodes())],
        ];
    }
}
