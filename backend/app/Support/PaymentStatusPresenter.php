<?php

declare(strict_types=1);

namespace App\Support;

/**
 * Display groups for the order payment states the platform actually stores (orders.status).
 */
final class PaymentStatusPresenter
{
    /** @var array<string, array{label: string, group: string, tone: string}> */
    private const STATUSES = [
        'awaiting_payment' => ['label' => 'Pending', 'group' => 'pending', 'tone' => 'neutral'],
        'payment_submitted' => ['label' => 'Confirmation submitted', 'group' => 'submitted', 'tone' => 'warning'],
        'under_verification' => ['label' => 'Confirmation submitted', 'group' => 'submitted', 'tone' => 'warning'],
        'paid' => ['label' => 'Verified', 'group' => 'verified', 'tone' => 'success'],
        'partially_paid' => ['label' => 'Partially verified', 'group' => 'verified', 'tone' => 'success'],
        'rejected' => ['label' => 'Rejected', 'group' => 'rejected', 'tone' => 'danger'],
        'refunded' => ['label' => 'Refunded', 'group' => 'refunded', 'tone' => 'info'],
        'cancelled' => ['label' => 'Cancelled', 'group' => 'closed', 'tone' => 'neutral'],
        'expired' => ['label' => 'Expired', 'group' => 'closed', 'tone' => 'neutral'],
    ];

    /** Filter groups shown in the console, each mapped to the stored statuses it covers. */
    public const GROUPS = [
        'pending' => ['label' => 'Pending', 'statuses' => ['awaiting_payment']],
        'submitted' => ['label' => 'Confirmation submitted', 'statuses' => ['payment_submitted', 'under_verification']],
        'verified' => ['label' => 'Verified', 'statuses' => ['paid', 'partially_paid']],
        'rejected' => ['label' => 'Rejected', 'statuses' => ['rejected']],
        'refunded' => ['label' => 'Refunded', 'statuses' => ['refunded']],
        'closed' => ['label' => 'Cancelled or expired', 'statuses' => ['cancelled', 'expired']],
    ];

    public static function isPaymentStatus(?string $status): bool
    {
        return isset(self::STATUSES[(string) $status]);
    }

    public static function label(?string $status): string
    {
        return self::STATUSES[$status]['label'] ?? ucwords(str_replace('_', ' ', (string) $status));
    }

    public static function tone(?string $status): string
    {
        return self::STATUSES[$status]['tone'] ?? 'neutral';
    }

    /** @return array<int, string> */
    public static function statusesFor(string $group): array
    {
        return self::GROUPS[$group]['statuses'] ?? [];
    }

    public static function methodLabel(?string $method): string
    {
        return match ($method) {
            'bank' => 'Bank transfer',
            'cash' => 'Cash',
            'card' => 'Card',
            'zelle' => 'Zelle',
            'cashapp' => 'Cash App',
            default => ucfirst((string) $method),
        };
    }
}
