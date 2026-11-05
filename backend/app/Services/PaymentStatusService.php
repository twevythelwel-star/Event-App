<?php

namespace App\Services;

use App\Models\Order;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class PaymentStatusService
{
    public const PENDING = 'pending';
    public const AWAITING_VERIFICATION = 'awaiting_verification';
    public const PROCESSING = 'processing';
    public const PAID = 'paid';
    public const FAILED = 'failed';
    public const PARTIALLY_REFUNDED = 'partially_refunded';
    public const REFUNDED = 'refunded';
    public const CANCELLED = 'cancelled';
    public const EXPIRED = 'expired';

    private const VALID_TRANSITIONS = [
        self::PENDING => [self::PROCESSING, self::FAILED, self::CANCELLED, self::EXPIRED],
        self::AWAITING_VERIFICATION => [self::PAID, self::FAILED, self::CANCELLED],
        self::PROCESSING => [self::PAID, self::FAILED],
        self::PAID => [self::PARTIALLY_REFUNDED, self::REFUNDED],
        self::PARTIALLY_REFUNDED => [self::REFUNDED],
    ];

    public function transition(Order $order, string $newStatus, User $actor): bool
    {
        return DB::transaction(function () use ($order, $newStatus, $actor) {
            $locked = Order::withoutGlobalScopes()->lockForUpdate()->findOrFail($order->id);
            $currentStatus = (string) $locked->status;
            if (!$this->isValidTransition($currentStatus, $newStatus)) {
                Log::notice('payment.status_transition_rejected', ['order_id' => $locked->id, 'from' => $currentStatus, 'to' => $newStatus]);
                throw new \InvalidArgumentException("Invalid payment status transition from {$currentStatus} to {$newStatus}");
            }
            $locked->status = $newStatus;
            $locked->save();
            \App\Services\AuditLogService::log(action: 'payment_status_changed', targetEntity: 'orders', targetId: (string) $locked->id,
                details: "Payment status changed from {$currentStatus} to {$newStatus} by {$actor->email}", actor: $actor);
            Log::info('payment.status_transition', ['order_id' => $locked->id, 'from' => $currentStatus, 'to' => $newStatus, 'actor_id' => $actor->id]);
            if ($newStatus === self::FAILED) {
                Log::error('payment.failed', ['order_id' => $locked->id, 'actor_id' => $actor->id, 'method' => $locked->method]);
                if (app()->environment('production')) {
                    Log::channel('slack')->error('payment.failed', ['order_id' => $locked->id, 'actor_id' => $actor->id, 'method' => $locked->method]);
                }
            }
            return true;
        });
    }

    private function isValidTransition(string $from, string $to): bool
    {
        return isset(self::VALID_TRANSITIONS[$from]) && in_array($to, self::VALID_TRANSITIONS[$from], true);
    }

    public static function isValidStatus(string $status): bool
    {
        return in_array($status, [
            self::PENDING,
            self::AWAITING_VERIFICATION,
            self::PROCESSING,
            self::PAID,
            self::FAILED,
            self::PARTIALLY_REFUNDED,
            self::REFUNDED,
            self::CANCELLED,
            self::EXPIRED,
        ]);
    }
}
