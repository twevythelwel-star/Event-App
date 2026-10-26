<?php

declare(strict_types=1);

namespace App\Services;

use App\Exceptions\SecurityException;
use App\Models\Event;
use App\Models\SecurityAuditLog;
use App\Models\StaffClaimCode;
use App\Models\User;
use Illuminate\Support\Str;

class StaffClaimCodeService
{
    /**
     * Characters used for readable, high-entropy claim codes (omitting ambiguous 0/O, 1/I/L).
     */
    private const CHARSET = '23456789ABCDEFGHJKMNPQRSTUVWXYZ';

    /**
     * Generate a cryptographically random, single-use, 10-minute staff claim code.
     * The raw code is returned to the user once and NEVER persisted in plaintext.
     *
     * @return array{plain_code: string, claim_code: StaffClaimCode}
     */
    public static function generateCode(
        User $user,
        int $lifetimeMinutes = 10,
        ?string $ipAddress = null,
        ?string $userAgent = null
    ): array {
        if ($user->status !== 'active') {
            throw new SecurityException('Suspended accounts cannot generate staff claim codes.');
        }

        // Revoke any existing active claim codes for this user (single-active-code invariant)
        StaffClaimCode::where('user_id', $user->id)
            ->where('status', 'active')
            ->get()
            ->each(function (StaffClaimCode $existing) {
                $existing->revoke();
            });

        // Generate high-entropy 8-character token formatted as STF-XXXX-XXXX
        $rawChars = '';
        $maxIndex = strlen(self::CHARSET) - 1;
        for ($i = 0; $i < 8; $i++) {
            $rawChars .= self::CHARSET[random_int(0, $maxIndex)];
        }
        $plainCode = 'STF-' . substr($rawChars, 0, 4) . '-' . substr($rawChars, 4, 4);

        $codeHash = hash('sha256', $plainCode);

        $claimCode = StaffClaimCode::create([
            'user_id' => $user->id,
            'code_hash' => $codeHash,
            'status' => 'active',
            'expires_at' => now()->modify("+{$lifetimeMinutes} minutes"),
            'ip_address' => $ipAddress,
            'user_agent' => $userAgent ? mb_substr($userAgent, 0, 500) : null,
        ]);

        AuditLogService::log(
            action: 'staff_claim_code_generated',
            targetEntity: 'staff_claim_codes',
            targetId: (string) $claimCode->id,
            details: "User {$user->email} generated an expiring staff claim code (expires in {$lifetimeMinutes}m).",
            actor: $user
        );

        return [
            'plain_code' => $plainCode,
            'claim_code' => $claimCode,
        ];
    }

    /**
     * Revoke an active claim code prior to redemption.
     */
    public static function revokeCode(User $user, int|string $codeId): bool
    {
        $code = StaffClaimCode::where('id', $codeId)
            ->where('user_id', $user->id)
            ->first();

        if (!$code) {
            return false;
        }

        if ($code->isRedeemed()) {
            throw new SecurityException('Cannot revoke an already-redeemed staff claim code.');
        }

        $code->revoke();

        AuditLogService::log(
            action: 'staff_claim_code_revoked',
            targetEntity: 'staff_claim_codes',
            targetId: (string) $code->id,
            details: "User {$user->email} revoked staff claim code ID {$code->id}.",
            actor: $user
        );

        return true;
    }

    /**
     * Validate a staff claim code for an Event Host and return minimal masked identification.
     * Prevents user enumeration by returning generic failure messages.
     *
     * @return array{valid: bool, claim_code?: StaffClaimCode, masked_name?: string, message?: string}
     */
    public static function validateAndPreview(User $host, Event $event, string $rawCode): array
    {
        $normalized = strtoupper(trim($rawCode));
        $hashed = hash('sha256', $normalized);

        $code = StaffClaimCode::where('code_hash', $hashed)->first();

        // Generic error message invariant to prevent user enumeration
        $genericError = 'Unable to verify this code. Ask the account holder to generate a new one.';

        if (!$code || !$code->isValid()) {
            return [
                'valid' => false,
                'message' => $genericError,
            ];
        }

        // Prohibit host from assigning themselves as staff
        if ($code->user_id === $host->id) {
            return [
                'valid' => false,
                'message' => 'Event Hosts cannot assign themselves as staff members.',
            ];
        }

        return [
            'valid' => true,
            'claim_code' => $code,
            'claim_code_id' => $code->id,
            'masked_name' => $code->getMaskedOwnerName(),
        ];
    }
}
