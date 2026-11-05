<?php

declare(strict_types=1);

namespace App\Payments;

/**
 * The payment states an order can be in (orders.status), and the only moves allowed between them.
 *
 *   awaiting_payment     Pending: nothing received or claimed yet
 *   payment_submitted    Awaiting manual verification: the customer says they sent a transfer
 *   under_verification   Awaiting manual verification: the organiser is checking
 *   processing           A payment processor has the payment and has not settled it yet
 *   partially_paid       Some money received and verified, not all
 *   paid                 Paid in full, verified or confirmed by the processor
 *   failed               The processor declined or the payment failed; the customer may try again
 *   rejected             The organiser could not find the claimed transfer
 *   partially_refunded   Paid, then part of it returned
 *   refunded             Everything received was returned
 *   cancelled, expired   Closed without payment
 *
 * Payment status is not admission status: whether a ticket has been scanned in is recorded separately
 * (scans and handovers). Payment status only decides whether the ticket may be admitted at all.
 */
final class PaymentStatus
{
    public const AWAITING_PAYMENT = 'awaiting_payment';
    public const PAYMENT_SUBMITTED = 'payment_submitted';
    public const UNDER_VERIFICATION = 'under_verification';
    public const PROCESSING = 'processing';
    public const PARTIALLY_PAID = 'partially_paid';
    public const PAID = 'paid';
    public const FAILED = 'failed';
    public const REJECTED = 'rejected';
    public const PARTIALLY_REFUNDED = 'partially_refunded';
    public const REFUNDED = 'refunded';
    public const CANCELLED = 'cancelled';
    public const EXPIRED = 'expired';

    /** Fully paid, possibly with part of it since refunded: the ticket can be admitted and food handed over. */
    public const SETTLED = [self::PAID, self::PARTIALLY_REFUNDED];

    /** Money has been received and verified (fully or in part). */
    public const MONEY_RECEIVED = [self::PAID, self::PARTIALLY_PAID, self::PARTIALLY_REFUNDED];

    /** The customer can still pay (or pay again after a failure). */
    public const PAYABLE = [self::AWAITING_PAYMENT, self::FAILED];

    /** No more money can move in or out, except by refunding what was received. */
    public const CLOSED = [self::REFUNDED, self::CANCELLED, self::EXPIRED];

    /** Moves the organiser's payment desk may make by hand, for manual methods (transfer, cash). */
    public const MANUAL_TRANSITIONS = [
        self::AWAITING_PAYMENT => [self::UNDER_VERIFICATION, self::PARTIALLY_PAID, self::PAID, self::REJECTED],
        self::PAYMENT_SUBMITTED => [self::UNDER_VERIFICATION, self::PARTIALLY_PAID, self::PAID, self::REJECTED],
        self::UNDER_VERIFICATION => [self::PARTIALLY_PAID, self::PAID, self::REJECTED],
        self::PARTIALLY_PAID => [self::PARTIALLY_PAID, self::PAID],
        self::REJECTED => [self::UNDER_VERIFICATION],
        self::PAID => [self::PAID],
    ];

    /** Moves made only from a processor's own confirmed state (server-side retrieval or a verified webhook). */
    public const PROCESSOR_TRANSITIONS = [
        self::AWAITING_PAYMENT => [self::PROCESSING, self::PAID, self::FAILED],
        self::FAILED => [self::PROCESSING, self::PAID, self::FAILED, self::AWAITING_PAYMENT],
        self::PROCESSING => [self::PROCESSING, self::PAID, self::FAILED, self::AWAITING_PAYMENT],
        self::PAID => [self::PAID],
    ];

    /** Refunds: only money that was received can be returned. */
    public const REFUND_FROM = [self::PAID, self::PARTIALLY_PAID, self::PARTIALLY_REFUNDED];

    private const LABELS = [
        self::AWAITING_PAYMENT => 'Pending',
        self::PAYMENT_SUBMITTED => 'Awaiting verification',
        self::UNDER_VERIFICATION => 'Awaiting verification',
        self::PROCESSING => 'Processing',
        self::PARTIALLY_PAID => 'Partly paid',
        self::PAID => 'Paid',
        self::FAILED => 'Payment failed',
        self::REJECTED => 'Rejected',
        self::PARTIALLY_REFUNDED => 'Partly refunded',
        self::REFUNDED => 'Refunded',
        self::CANCELLED => 'Cancelled',
        self::EXPIRED => 'Expired',
    ];

    /** @return list<string> */
    public static function all(): array
    {
        return array_keys(self::LABELS);
    }

    public static function label(?string $status): string
    {
        return self::LABELS[(string) $status] ?? ucwords(str_replace('_', ' ', (string) $status));
    }

    public static function isSettled(?string $status): bool
    {
        return in_array($status, self::SETTLED, true);
    }

    public static function canProcessorMove(string $from, string $to): bool
    {
        return in_array($to, self::PROCESSOR_TRANSITIONS[$from] ?? [], true);
    }

    /**
     * The status after a refund, from what was received and what has now been returned in total. A partly paid
     * order stays partly paid after a partial refund: it was never settled, so it must not become admissible.
     */
    public static function afterRefund(string $from, int $receivedMinor, int $refundedMinor): string
    {
        if ($refundedMinor >= $receivedMinor) {
            return self::REFUNDED;
        }

        return $from === self::PARTIALLY_PAID ? self::PARTIALLY_PAID : self::PARTIALLY_REFUNDED;
    }
}
