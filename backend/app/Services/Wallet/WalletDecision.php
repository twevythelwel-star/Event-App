<?php

declare(strict_types=1);

namespace App\Services\Wallet;

/**
 * The one answer the WalletPassManager gives about a ticket and a wallet provider: every question the wallet flow
 * depends on, and the single action that follows from them.
 *
 * Actions:
 *   unavailable - the provider is not configured (or not approved): nothing to offer, nothing can be sent
 *   ineligible  - no pass exists and the ticket may not have one (not the holder, unpaid, void, past, used)
 *   offer       - no pass yet and the ticket may have one: the "Add to …" button can show (if offerable)
 *   up_to_date  - the provider already has the current state
 *   update      - the pass exists and the event, the ticket or its QR changed since the provider last got it
 *   revoke      - the pass exists but the ticket is no longer valid for its holder: remove the barcode
 */
final class WalletDecision
{
    public const UNAVAILABLE = 'unavailable';

    public const INELIGIBLE = 'ineligible';

    public const OFFER = 'offer';

    public const UP_TO_DATE = 'up_to_date';

    public const UPDATE = 'update';

    public const REVOKE = 'revoke';

    public function __construct(
        public readonly string $provider,
        public readonly string $action,
        public readonly bool $eligible,
        public readonly ?string $ineligibleReason,
        public readonly bool $configured,
        public readonly bool $available,
        public readonly bool $passExists,
        public readonly bool $saved,
        public readonly bool $needsUpdate,
        public readonly bool $revoked,
        public readonly bool $eventChanged,
        public readonly bool $reissued,
    ) {}

    /** Something has to be sent to the provider. */
    public function requiresSync(): bool
    {
        return $this->action === self::UPDATE || $this->action === self::REVOKE;
    }

    public function toArray(): array
    {
        return get_object_vars($this);
    }
}
