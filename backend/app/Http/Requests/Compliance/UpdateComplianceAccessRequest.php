<?php

declare(strict_types=1);

namespace App\Http\Requests\Compliance;

use App\Support\AdminAuthorization as Admin;
use Illuminate\Foundation\Http\FormRequest;

/** Granting or removing identity document access (and compliance management): another system administrator only. */
class UpdateComplianceAccessRequest extends FormRequest
{
    public function authorize(): bool
    {
        return (bool) $this->user()?->can(Admin::IDENTITY_ACCESS_GRANT, $this->route('user'));
    }

    public function rules(): array
    {
        return [
            'grant' => ['required', 'boolean'],
            'manage' => ['nullable', 'boolean'],
            'reason' => ['required', 'string', 'min:10', 'max:500'],
            'confirm' => ['accepted'],
        ];
    }
}
