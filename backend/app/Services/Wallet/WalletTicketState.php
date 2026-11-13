<?php

declare(strict_types=1);

namespace App\Services\Wallet;

use App\Models\Order;
use App\Models\WalletPass;
use App\Support\TicketLifecycle;

/**
 * What a wallet pass should show for its ticket right now, for every provider: the status label, whether it may carry
 * the admission barcode, and whether it is void (transferred, revoked, refunded, cancelled). Rebuilt from the order
 * every time, so a pass can never admit anyone the ticket record would not.
 */
final class WalletTicketState
{
    /** @return array{status: string, code: string, barcode: bool, voided: bool} */
    public static function of(WalletPass $pass, Order $order): array
    {
        $status = (string) $order->status;

        return match (true) {
            $pass->voided_at !== null || self::holderChanged($pass, $order) => ['status' => 'Transferred', 'code' => 'transferred', 'barcode' => false, 'voided' => true],
            $order->token_revoked_at !== null => ['status' => 'Revoked', 'code' => 'revoked', 'barcode' => false, 'voided' => true],
            $status === 'refunded' => ['status' => 'Refunded', 'code' => 'refunded', 'barcode' => false, 'voided' => true],
            in_array($status, TicketLifecycle::CANCELLED_STATUSES, true) => ['status' => 'Cancelled', 'code' => 'cancelled', 'barcode' => false, 'voided' => true],
            TicketLifecycle::eventIsOver($order->event) => ['status' => 'Event over', 'code' => 'past', 'barcode' => false, 'voided' => false],
            !in_array($status, TicketLifecycle::SETTLED_STATUSES, true) => ['status' => 'Awaiting payment', 'code' => 'unpaid', 'barcode' => false, 'voided' => false],
            $order->checked_in_at !== null => ['status' => 'Checked in', 'code' => 'checked_in', 'barcode' => true, 'voided' => false],
            default => ['status' => 'Valid for entry', 'code' => 'valid', 'barcode' => true, 'voided' => false],
        };
    }

    public static function holderChanged(WalletPass $pass, Order $order): bool
    {
        return $order->user_id !== null && (int) $pass->user_id !== (int) $order->user_id;
    }
}
