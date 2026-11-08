<?php

declare(strict_types=1);

namespace App\Events\Compliance;

use App\Models\HostIdentityVerification;
use App\Models\User;
use Illuminate\Contracts\Events\ShouldDispatchAfterCommit;
use Illuminate\Foundation\Events\Dispatchable;

/**
 * A host's government ID verification changed status. HostIdentityVerificationService writes its own PII audit
 * entry for this; the event lets the host be told by email (SendComplianceNotifications).
 */
final class IdentityVerificationReviewed implements ShouldDispatchAfterCommit
{
    use Dispatchable;

    public function __construct(
        public readonly HostIdentityVerification $verification,
        public readonly User $reviewer,
        public readonly string $previousStatus,
    ) {
    }
}
