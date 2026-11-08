<?php

declare(strict_types=1);

namespace App\Http\Requests\Compliance;

use Illuminate\Foundation\Http\FormRequest;

/** Publishing a drafted policy version: a system administrator, confirming who must accept it. */
class PublishPolicyVersionRequest extends FormRequest
{
    public function authorize(): bool
    {
        return (bool) $this->user()?->can('publish', $this->route('version'));
    }

    public function rules(): array
    {
        return ['confirm' => ['accepted']];
    }

    public function messages(): array
    {
        return ['confirm.accepted' => 'Tick the box to confirm you understand who must accept it.'];
    }
}
