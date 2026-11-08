<?php

declare(strict_types=1);

namespace App\Http\Requests\Compliance;

use App\Support\AdminAuthorization as Admin;

/** Revoking an override, with a reason. */
class RevokePublicationOverrideRequest extends ComplianceReasonRequest
{
    protected string $field = 'revoke_reason';
    protected int $min = 5;

    public function authorize(): bool
    {
        return (bool) $this->user()?->can(Admin::COMPLIANCE_OVERRIDE);
    }
}
