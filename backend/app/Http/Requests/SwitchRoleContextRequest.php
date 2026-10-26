<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class SwitchRoleContextRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user() !== null && $this->user()->isActive();
    }

    public function rules(): array
    {
        return [
            'target_role' => ['required', 'string', 'in:system_admin,admin,event_host,attendee'],
        ];
    }
}
