<?php

declare(strict_types=1);

namespace App\Services;

use App\Exceptions\SecurityException;
use App\Models\Event;
use App\Models\Order;
use App\Models\User;
use Illuminate\Support\Facades\DB;

/**
 * Centralized ticket verification service for validating tickets against authoritative server-side data.
 *
 * This service enforces all ticket validation requirements:
 * - Ticket identity verification
 * - Event identity verification
 * - Payment or authorized admission state
 * - Revocation status
 * - Prior admission (duplicate redemption prevention)
 * - Ticket restrictions
 *
 * QR payloads do NOT contain payment details or sensitive personal information.
 */
class TicketVerificationService
{
    /**
     * Verify a ticket for admission.
     *
     * @param string $identifier Either secure_ticket_token (tok_...), ticket_code, or order_number
     * @param Event $event The event being checked into
     * @param User $verifier The user performing the verification
     * @param bool $manual Whether this is a manual verification (typed code vs QR scan)
     * @return array{success: bool, valid: bool, already_admitted: bool, rejection_reason: ?string, ticket: array}
     */
    public function verifyTicket(
        string $identifier,
        Event $event,
        User $verifier,
        bool $manual = false,
        ?string $requestId = null
    ): array {
        $identifier = trim($identifier);

        // Architecture A vs B guardrail: Public Event QR codes can never be used for gate admission
        if (\App\Services\EventQr\EventQrService::isPromotionalEventQrPayload($identifier)) {
            $reason = 'Promotional Event QR scanned — DO NOT ADMIT. Public event QR codes only open the event page and cannot admit guests.';
            AuditLogService::log(
                action: 'ticket_redemption_rejected',
                targetEntity: 'events',
                targetId: (string) $event->id,
                details: "Public promotional event QR scanned at gate by {$verifier->email} ({$identifier}). Reason: {$reason}",
                actor: $verifier,
                organizerId: (string) $event->organizer_id,
                eventId: (string) $event->id
            );

            return [
                'success' => false,
                'valid' => false,
                'already_admitted' => false,
                'promotional_qr_rejected' => true,
                'rejection_reason' => $reason,
                'ticket' => [],
            ];
        }

        $isToken = str_starts_with($identifier, 'tok_');

        // Find the order by SHA-256 token hash (or ticket_code / order_number for supervisor manual verification)
        $order = Order::withoutGlobalScopes()->whereNull('deleted_at')
            ->where('event_id', $event->id)
            ->when($isToken,
                fn ($q) => $q->whereTicketToken($identifier),
                fn ($q) => $q->where(fn ($w) => $w->where('ticket_code', $identifier)->orWhere('order_number', $identifier)))
            ->first();

        if (!$order) {
            $reason = 'No ticket for this event matches that code.';
            AuditLogService::log(
                action: 'ticket_redemption_rejected',
                targetEntity: 'events',
                targetId: (string) $event->id,
                details: "Unrecognized, fake, or replaced ticket identifier rejected at gate by {$verifier->email}.",
                actor: $verifier,
                organizerId: (string) $event->organizer_id,
                eventId: (string) $event->id
            );

            return [
                'success' => false,
                'valid' => false,
                'already_admitted' => false,
                'rejection_reason' => $reason,
                'ticket' => [],
            ];
        }

        // Verify the ticket with row locking to prevent concurrent redemption
        return DB::transaction(function () use ($order, $event, $verifier, $manual, $identifier, $requestId) {
            $locked = Order::query()->withoutGlobalScopes()->where('id', $order->id)->lockForUpdate()->first();

            if ($locked !== null && $locked !== $order) {
                $order->checked_in_at = $locked->checked_in_at;
                $order->checked_in_by = $locked->checked_in_by;
                $order->status = $locked->status;
            }

            $locked ??= $order;
            if ($requestId !== null && hash_equals((string) $locked->check_in_request_id, $requestId)
                && (int) $locked->checked_in_by === (int) $verifier->id && $locked->checked_in_at !== null) {
                return ['success' => true, 'valid' => true, 'already_admitted' => false, 'replayed' => true,
                    'rejection_reason' => null, 'ticket' => $this->sanitizeTicketData($locked)];
            }

            return $this->verifyLockedTicket($locked, $event, $verifier, $manual, $identifier, $requestId);
        });
    }

    /**
     * Verify a ticket that is already locked for update.
     *
     * @param Order $order The locked order row
     * @param Event $event The event
     * @param User $verifier The user performing verification
     * @param bool $manual Whether this is manual verification
     * @param string $identifier The original identifier
     * @return array{success: bool, valid: bool, already_admitted: bool, rejection_reason: ?string, ticket: array}
     */
    private function verifyLockedTicket(
        Order $order,
        Event $event,
        User $verifier,
        bool $manual,
        string $identifier,
        ?string $requestId
    ): array {
        $rejectionReason = null;

        // 1. Ticket identity check
        if (empty($order->ticket_code) || empty($order->order_number)) {
            $rejectionReason = 'Invalid ticket: missing ticket code or order number.';
        }

        // 2. Event identity check
        if ((string) $order->event_id !== (string) $event->id) {
            $rejectionReason = $rejectionReason ?? "Event mismatch: Ticket belongs to event {$order->event_id}, not {$event->id}.";
        }

        // 3. Revocation status check (immediate server-side revocation or terminal revoked status)
        if ($order->isTicketRevoked()) {
            $detail = $order->revocation_reason ?: "status: {$order->status}";
            $rejectionReason = $rejectionReason ?? "Ticket has been revoked ({$detail}).";
        }

        // 4. Payment or authorized admission state check
        $isPaidOrAuthorized = in_array($order->status, ['paid', 'confirmed'], true) || $order->authorized_unpaid === true;
        if (!$isPaidOrAuthorized) {
            $rejectionReason = $rejectionReason ?? "Invalid admission state: Ticket status is '{$order->status}'. Must be paid, confirmed, or authorized unpaid.";
        }

        // 5. Prior admission check (duplicate redemption prevention)
        $isAlreadyAdmitted = !empty($order->checked_in_at);
        if ($isAlreadyAdmitted) {
            $checkInStr = $order->checked_in_at instanceof \DateTimeInterface
                ? $order->checked_in_at->format('Y-m-d H:i:s')
                : (string) $order->checked_in_at;
            $rejectionReason = $rejectionReason ?? "Duplicate redemption prevented: Ticket was already admitted at {$checkInStr}.";
        }

        // 6. Ticket restrictions check
        $restrictionFailure = $this->checkTicketRestrictions($order);
        if ($restrictionFailure) {
            $rejectionReason = $rejectionReason ?? $restrictionFailure;
        }

        $isValid = $rejectionReason === null;

        // If valid, record the admission
        if ($isValid) {
            $order->checked_in_at = now();
            $order->checked_in_by = $verifier->id;
            $order->check_in_request_id = $requestId;
            $order->save();

            AuditLogService::log(
                action: 'ticket_redemption_verified',
                targetEntity: 'orders',
                targetId: (string) $order->id,
                details: "Ticket {$order->ticket_code} (Order #{$order->order_number}) successfully verified and redeemed by {$verifier->email}"
                    . ($manual ? ' using typed code (manual check).' : ' by QR scan.'),
                actor: $verifier
            );
        } else {
            if ($isAlreadyAdmitted) {
                \Illuminate\Support\Facades\Log::warning('ticket.redemption_conflict', [
                    'event_id' => $event->id, 'order_id' => $order->id, 'verifier_id' => $verifier->id,
                ]);
            }
            AuditLogService::log(
                action: 'ticket_redemption_rejected',
                targetEntity: 'orders',
                targetId: (string) $order->id,
                details: "Ticket {$order->ticket_code} redemption rejected by {$verifier->email}. Reason: {$rejectionReason}",
                actor: $verifier
            );
        }

        // Return sanitized ticket data (no payment details or sensitive PII)
        return [
            'success' => $isValid,
            'valid' => $isValid,
            'already_admitted' => $isAlreadyAdmitted,
            'rejection_reason' => $rejectionReason,
            'ticket' => $this->sanitizeTicketData($order),
        ];
    }

    /**
     * Check ticket-specific restrictions.
     *
     * @return string|null Reason for rejection, or null if no restrictions fail
     */
    private function checkTicketRestrictions(Order $order): ?string
    {
        // Check if ticket has time-based restrictions
        if ($order->checked_in_at && $order->checked_in_at > now()) {
            return 'Ticket has a future admission time restriction.';
        }

        // Check if event is still active
        if ($order->event && $order->event->status !== 'published') {
            return 'Event is not currently active for admission.';
        }

        // A finished event's tickets are keepsakes, never valid for admission again.
        if ($order->event && \App\Support\TicketLifecycle::eventIsOver($order->event)) {
            return 'This event has ended; admission is closed.';
        }

        // Check if ticket has any custom restrictions (stored in ticket_restrictions JSON field)
        if (!empty($order->ticket_restrictions) && is_array($order->ticket_restrictions)) {
            foreach ($order->ticket_restrictions as $restriction) {
                if (isset($restriction['type']) && isset($restriction['value'])) {
                    $failure = $this->evaluateRestriction($restriction);
                    if ($failure) {
                        return $failure;
                    }
                }
            }
        }

        return null;
    }

    /**
     * Evaluate a single ticket restriction.
     *
     * @param array{type: string, value: mixed} $restriction
     * @return string|null Reason for rejection, or null if restriction passes
     */
    private function evaluateRestriction(array $restriction): ?string
    {
        return match ($restriction['type']) {
            'max_uses' => $this->checkMaxUses($restriction['value']),
            'time_window' => $this->checkTimeWindow($restriction['value']),
            'age_restriction' => $this->checkAgeRestriction($restriction['value']),
            default => null,
        };
    }

    /**
     * Check maximum uses restriction.
     */
    private function checkMaxUses(int $maxUses): ?string
    {
        // This would need to track actual uses per ticket
        // For now, this is a placeholder for future implementation
        return null;
    }

    /**
     * Check time window restriction.
     */
    private function checkTimeWindow(array $window): ?string
    {
        $now = now();
        $start = $window['start'] ?? null;
        $end = $window['end'] ?? null;

        if ($start && $now < $start) {
            return 'Ticket is not yet valid for admission.';
        }

        if ($end && $now > $end) {
            return 'Ticket admission window has expired.';
        }

        return null;
    }

    /**
     * Check age restriction.
     */
    private function checkAgeRestriction(int $minAge): ?string
    {
        // This would need customer date of birth
        // For now, this is a placeholder for future implementation
        return null;
    }

    /**
     * Sanitize ticket data for display.
     * Removes payment details and sensitive personal information.
     *
     * @return array
     */
    private function sanitizeTicketData(Order $order): array
    {
        return [
            'order_number' => $order->order_number,
            'ticket_code' => $order->ticket_code,
            'customer_name' => $order->customer_name,
            'status' => $order->status,
            'checked_in_at' => $order->checked_in_at?->format('Y-m-d H:i:s'),
            // Explicitly excluded: payment_method, total_cents, customer_email, customer_phone
        ];
    }

    /**
     * Validate that a QR token is secure and unguessable.
     *
     * @param string $token The token to validate
     * @return bool
     */
    public function isValidSecureToken(string $token): bool
    {
        // Must start with 'tok_' prefix
        if (!str_starts_with($token, 'tok_')) {
            return false;
        }

        // Must be at least 40 characters total (tok_ + 35+ chars of entropy)
        if (strlen($token) < 40) {
            return false;
        }

        // Must contain only URL-safe characters
        if (!preg_match('/^tok_[A-Za-z0-9_-]+$/', $token)) {
            return false;
        }

        return true;
    }

    /**
     * Validate that a QR token has at least 256 bits of cryptographic entropy (tok_ + 64 hex chars).
     */
    public function has256BitEntropy(string $token): bool
    {
        return (bool) preg_match('/^tok_[a-f0-9]{64}$/i', $token);
    }

    /**
     * Generate a new secure ticket token with 256 bits of CSPRNG randomness.
     *
     * @return string
     */
    public static function generateSecureToken(): string
    {
        return Order::generateSecureTicketToken();
    }
}
