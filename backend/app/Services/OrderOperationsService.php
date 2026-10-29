<?php

namespace App\Services;

use App\Enums\AccountType;
use App\Enums\TenantRole;
use App\Exceptions\SecurityException;
use App\Models\EventWorkerAssignment;
use App\Models\HandoverAuditLog;
use App\Models\NotificationLog;
use App\Models\Order;
use App\Models\OrderItem;
use App\Models\OrganizerUser;
use App\Models\SecurityAuditLog;
use App\Models\User;
use App\Services\Notifications\NotificationService;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

/**
 * Event-day order operations for hosts and staff: payment verification, kitchen status, and portion handover.
 *
 * Access is always resolved against the order's own organizer (membership role) or the order's event
 * (active staff assignment); a global Event Host role never grants access to another organizer's orders.
 */
class OrderOperationsService
{
    /** Payment statuses a host may set by hand. Refunds go through the dedicated refund flow. */
    public const MANUAL_PAYMENT_STATUSES = ['under_verification', 'partially_paid', 'paid', 'rejected'];

    public const FULFILLMENT_STATUSES = ['unprepared', 'preparing', 'ready_for_pickup', 'collected'];

    /**
     * @return array{verify: bool, collect: bool, scan: bool, cash: bool, reverse: bool, refund: bool}
     */
    public function capabilities(User $user, Order $order): array
    {
        return $this->capabilitiesFor($user, $order->organizer_id, $order->event_id);
    }

    /**
     * What a user may do at one organizer's event: via organizer membership, or else an active staff assignment.
     * Reversals and refunds are reserved for event owners (cashiers cannot unwind money they recorded).
     *
     * @return array{verify: bool, collect: bool, scan: bool, cash: bool, reverse: bool, refund: bool}
     */
    public function capabilitiesFor(User $user, string $organizerId, ?string $eventId): array
    {
        if ($user->hasRole(AccountType::SYSTEM_ADMIN)) {
            return ['verify' => true, 'collect' => true, 'scan' => true, 'cash' => true, 'reverse' => true, 'refund' => true];
        }

        $membership = OrganizerUser::where('organizer_id', $organizerId)
            ->where('user_id', $user->id)
            ->where('is_active', true)
            ->first();

        $role = $membership?->role instanceof TenantRole
            ? $membership->role
            : ($membership ? TenantRole::tryFrom((string) $membership->role) : null);

        if ($role) {
            $isOwner = in_array($role, [TenantRole::PLATFORM_ADMIN, TenantRole::EVENT_OWNER], true);

            return [
                'verify' => $role->canVerifyPayments(),
                'collect' => $role->canCollectFood(),
                'scan' => $role->canScanTickets() || $role->canCollectFood(),
                'cash' => $role->canVerifyPayments(),
                'reverse' => $isOwner,
                'refund' => $isOwner,
            ];
        }

        $assignment = $eventId ? $this->activeAssignment($user, $eventId) : null;
        if ($assignment) {
            return [
                'verify' => $assignment->canPerform('verify_payments'),
                'collect' => $assignment->canPerform('mark_quantities_collected'),
                'scan' => $assignment->canPerform('scan_qr_tickets') || $assignment->canPerform('mark_quantities_collected'),
                'cash' => $assignment->canPerform('record_cash_received'),
                'reverse' => false,
                'refund' => false,
            ];
        }

        return ['verify' => false, 'collect' => false, 'scan' => false, 'cash' => false, 'reverse' => false, 'refund' => false];
    }

    /**
     * Orders this user may operate on in some capacity, newest first.
     */
    public function operableOrdersQuery(User $user): Builder
    {
        $query = Order::withoutGlobalScopes()->with(['items', 'event'])->latest();

        if ($user->hasRole(AccountType::SYSTEM_ADMIN)) {
            return $query;
        }

        $reach = $this->reach($user);

        return $query->where(function (Builder $q) use ($reach) {
            $q->whereIn('organizer_id', $reach['organizers'])->orWhereIn('event_id', $reach['events']);
        });
    }

    public function findByCode(User $user, string $code): ?Order
    {
        $code = trim($code);
        if ($code === '') {
            return null;
        }

        return $this->operableOrdersQuery($user)
            ->where(function (Builder $q) use ($code) {
                $q->where('ticket_code', strtoupper($code))
                    ->orWhere('order_number', strtoupper($code))
                    ->orWhere('secure_ticket_token', $code);
            })
            ->first();
    }

    /**
     * @param array{status: string, amount_paid?: float|null, notes?: string|null, rejection_reason?: string|null} $data
     */
    public function updatePaymentStatus(User $user, Order $order, array $data): Order
    {
        $this->assertCapability($user, $order, 'verify', 'verify payments for this order');

        $status = $data['status'];
        if (!in_array($status, self::MANUAL_PAYMENT_STATUSES, true)) {
            throw new \InvalidArgumentException("Unsupported payment status '{$status}'.");
        }
        if ($order->status === 'refunded') {
            throw new \InvalidArgumentException('Refunded orders cannot change payment status.');
        }

        $total = (float) $order->total_amount;
        $notes = trim((string) ($data['notes'] ?? ''));

        if ($status === 'paid') {
            $order->amount_paid = $total;
        } elseif ($status === 'partially_paid') {
            $amount = (float) ($data['amount_paid'] ?? 0);
            if ($amount <= 0 || $amount >= $total) {
                throw new \InvalidArgumentException('A partial payment must be more than zero and less than the order total.');
            }
            $order->amount_paid = $amount;
        } elseif ($status === 'rejected') {
            $reason = trim((string) ($data['rejection_reason'] ?? ''));
            if ($reason === '') {
                throw new \InvalidArgumentException('A rejection reason is required.');
            }
            $order->rejection_reason = $reason;
        }

        $previousStatus = $order->status;
        $order->status = $status;

        if (in_array($status, ['paid', 'partially_paid'], true)) {
            $order->verified_at = now();
            $order->verified_by = $user->name;
        }
        if ($notes !== '') {
            $order->verification_notes = $notes;
        }
        $order->save();

        $this->audit($user, 'order_payment_status_changed', $order, "Order {$order->order_number}: payment status {$previousStatus} -> {$status}.");

        if ($status === 'paid' && $previousStatus !== 'paid') {
            NotificationService::notifyOrder($order, 'payment_verified');
        } elseif ($status === 'rejected' && $previousStatus !== 'rejected') {
            NotificationService::notifyOrder($order, 'payment_rejected');
        }

        return $order->fresh(['items', 'event']);
    }

    public function updateFulfillmentStatus(User $user, Order $order, string $status): Order
    {
        $this->assertCapability($user, $order, 'collect', 'update kitchen status for this order');

        if (!in_array($status, self::FULFILLMENT_STATUSES, true)) {
            throw new \InvalidArgumentException("Unsupported fulfillment status '{$status}'.");
        }
        if ($status === 'collected' && !$order->all_handed_over) {
            throw new \InvalidArgumentException('Mark portions as handed over (or close the order) before marking it collected.');
        }

        $previousStatus = $order->fulfillment_status;
        $order->fulfillment_status = $status;
        $order->save();

        if ($status === 'ready_for_pickup' && $previousStatus !== 'ready_for_pickup') {
            NotificationService::notifyOrder($order, 'pickup_instructions');
        }

        return $order->fresh(['items', 'event']);
    }

    /**
     * Record portions handed over (positive delta) or a correction (negative delta) for one order item.
     *
     * @param array{station?: string|null, action_type?: string|null, override_reason?: string|null, supervisor_name?: string|null} $audit
     * @return array{order: Order, audit: HandoverAuditLog}
     */
    public function recordHandover(User $user, Order $order, string $orderItemId, int $delta, array $audit): array
    {
        $this->assertCapability($user, $order, 'collect', 'hand over food for this order');

        if ($delta === 0) {
            throw new \InvalidArgumentException('Quantity change must not be zero.');
        }

        $actionType = $audit['action_type'] ?? ($delta > 0 ? 'collection' : 'reversal');
        $overrideReason = trim((string) ($audit['override_reason'] ?? ''));

        if (!in_array($actionType, ['collection', 'reversal', 'supervisor_override'], true)) {
            throw new \InvalidArgumentException("Unsupported handover action '{$actionType}'.");
        }
        if ($delta < 0 && $actionType === 'collection') {
            throw new \InvalidArgumentException('Use a reversal to reduce collected portions.');
        }
        // Unpaid orders only release food under an explicit, reasoned supervisor override
        if ($delta > 0 && !$order->isEligibleForHandover() && ($actionType !== 'supervisor_override' || $overrideReason === '')) {
            throw new \InvalidArgumentException('Payment has not been verified for this order. A supervisor override with a reason is required.');
        }
        if (in_array($actionType, ['reversal', 'supervisor_override'], true) && $overrideReason === '') {
            throw new \InvalidArgumentException('A reason is required for reversals and supervisor overrides.');
        }

        return DB::transaction(function () use ($user, $order, $orderItemId, $delta, $audit, $actionType, $overrideReason) {
            /** @var OrderItem|null $item */
            $item = OrderItem::where('id', $orderItemId)->where('order_id', $order->id)->lockForUpdate()->first();
            if (!$item) {
                throw new \InvalidArgumentException('Order item not found on this order.');
            }

            $collected = (int) $item->collected_quantity + $delta;
            if ($collected < 0 || $collected > $item->quantity) {
                $remaining = $item->quantity - (int) $item->collected_quantity;
                throw new \InvalidArgumentException("Cannot change by {$delta}: {$item->collected_quantity} of {$item->quantity} collected ({$remaining} remaining).");
            }

            // The audit trail names the authenticated account, never a client-supplied name
            $staffName = $user->name;
            $station = trim((string) ($audit['station'] ?? '')) ?: 'all';

            $item->collected_quantity = $collected;
            $item->handover_status = $collected >= $item->quantity ? 'collected' : 'pending';
            $item->handed_over_at = $collected >= $item->quantity ? ($item->handed_over_at ?? now()) : null;
            $item->handed_over_by = $staffName;
            $item->station_handed_over = $station;
            $item->save();

            $log = HandoverAuditLog::create([
                'organizer_id' => $order->organizer_id,
                'order_id' => $order->id,
                'order_number' => $order->order_number,
                'ticket_code' => $order->ticket_code,
                'customer_name' => $order->customer_name,
                'item_name' => $item->name,
                'item_category' => $item->category,
                'quantity_collected' => abs($delta),
                'remaining_quantity' => $item->quantity - $collected,
                'timestamp' => now(),
                'staff_name' => $staffName,
                'station' => $station,
                'action_type' => $actionType,
                'override_reason' => $overrideReason ?: null,
                'supervisor_name' => $audit['supervisor_name'] ?? null,
            ]);

            $this->refreshHandoverTotals($order);

            return ['order' => $order->fresh(['items', 'event']), 'audit' => $log];
        });
    }

    /**
     * Hand over every remaining portion and close the order.
     */
    public function closeOrder(User $user, Order $order, ?string $station = null): Order
    {
        $this->assertCapability($user, $order, 'collect', 'close this order');

        if (!$order->isEligibleForHandover()) {
            throw new \InvalidArgumentException('Payment has not been verified for this order, so it cannot be closed.');
        }

        return DB::transaction(function () use ($user, $order, $station) {
            $staffName = $user->name;
            $station = trim((string) $station) ?: 'all';

            foreach ($order->items()->lockForUpdate()->get() as $item) {
                $remaining = $item->quantity - (int) $item->collected_quantity;
                if ($remaining <= 0) {
                    continue;
                }

                $item->collected_quantity = $item->quantity;
                $item->handover_status = 'collected';
                $item->handed_over_at = $item->handed_over_at ?? now();
                $item->handed_over_by = $staffName;
                $item->station_handed_over = $station;
                $item->save();

                HandoverAuditLog::create([
                    'organizer_id' => $order->organizer_id,
                    'order_id' => $order->id,
                    'order_number' => $order->order_number,
                    'ticket_code' => $order->ticket_code,
                    'customer_name' => $order->customer_name,
                    'item_name' => $item->name,
                    'item_category' => $item->category,
                    'quantity_collected' => $remaining,
                    'remaining_quantity' => 0,
                    'timestamp' => now(),
                    'staff_name' => $staffName,
                    'station' => $station,
                    'action_type' => 'collection',
                ]);
            }

            $this->refreshHandoverTotals($order);

            return $order->fresh(['items', 'event']);
        });
    }

    private function refreshHandoverTotals(Order $order): void
    {
        $items = $order->items()->get();
        $allDone = $items->isNotEmpty() && $items->every(fn (OrderItem $i) => (int) $i->collected_quantity >= $i->quantity);
        $wasDone = (bool) $order->all_handed_over;

        $order->all_handed_over = $allDone;
        if ($allDone) {
            $order->fulfillment_status = 'collected';
            $order->closed_at = $order->closed_at ?? now();
            if (!$wasDone) {
                NotificationService::notifyOrder($order, 'order_collection_completed');
            }
        } else {
            $order->closed_at = null;
            if ($order->fulfillment_status === 'collected') {
                $order->fulfillment_status = 'ready_for_pickup';
            }
        }
        $order->save();
    }

    private function assertCapability(User $user, Order $order, string $capability, string $action): void
    {
        if (!$this->capabilities($user, $order)[$capability]) {
            throw new SecurityException("Unauthorized: you are not permitted to {$action}.");
        }
    }

    /**
     * Organizer ids where the user is an active member, and event ids where they hold an active staff assignment.
     *
     * @return array{organizers: \Illuminate\Support\Collection, events: \Illuminate\Support\Collection}
     */
    public function reach(User $user): array
    {
        return [
            'organizers' => OrganizerUser::where('user_id', $user->id)->where('is_active', true)->pluck('organizer_id'),
            'events' => EventWorkerAssignment::where('user_id', $user->id)
                ->where('is_active', true)
                ->get()
                ->filter(fn (EventWorkerAssignment $assignment) => $assignment->isCurrentlyActive())
                ->pluck('event_id'),
        ];
    }

    /**
     * Customer messages for orders whose payments this user may handle (messages carry customer contact details).
     */
    public function notificationsQuery(User $user): Builder
    {
        $orderIds = $this->operableOrdersQuery($user)->get()
            ->filter(fn (Order $order) => $this->capabilities($user, $order)['verify'])
            ->pluck('id');

        return NotificationLog::withoutGlobalScopes()->whereIn('order_id', $orderIds)->latest();
    }

    public function retryNotification(User $user, string $logId): NotificationLog
    {
        $log = $this->notificationsQuery($user)->whereKey($logId)->first();
        if (!$log) {
            throw (new ModelNotFoundException('Message not found.'))->setModel(NotificationLog::class);
        }

        return app(NotificationService::class)->retry($log);
    }

    /**
     * Remind every ticket holder of an event (paid or authorized orders), at most once per day per order.
     *
     * @return int Number of orders reminded
     */
    public function sendEventReminders(User $user, string $eventId): int
    {
        $orders = $this->operableOrdersQuery($user)
            ->where('event_id', $eventId)
            ->whereNotIn('status', ['refunded', 'rejected'])
            ->get()
            ->filter(fn (Order $order) => $this->capabilities($user, $order)['verify'])
            ->filter(fn (Order $order) => $order->status === 'paid' || $order->authorized_unpaid);

        if ($orders->isEmpty()) {
            throw new \InvalidArgumentException('There are no paid or authorized orders to remind for this event.');
        }

        $notifications = app(NotificationService::class);
        foreach ($orders as $order) {
            $notifications->queueForOrder($order, 'event_reminders', [], now()->format('Y-m-d'));
        }

        return $orders->count();
    }

    private function activeAssignment(User $user, string $eventId): ?EventWorkerAssignment
    {
        return EventWorkerAssignment::where('user_id', $user->id)
            ->where('event_id', $eventId)
            ->where('is_active', true)
            ->get()
            ->first(fn (EventWorkerAssignment $assignment) => $assignment->isCurrentlyActive());
    }

    private function audit(User $user, string $action, Order $order, string $details): void
    {
        SecurityAuditLog::create([
            'id' => (string) Str::uuid(),
            'user_id' => $user->id,
            'actor_role' => $user->getActiveRole()->value,
            'action' => $action,
            'target_entity' => 'orders',
            'target_id' => (string) $order->id,
            'ip_address' => request()->ip() ?? '127.0.0.1',
            'user_agent' => request()->userAgent() ?? 'api',
            'details' => $details,
            'is_pii_access' => false,
            'organizer_id' => $order->organizer_id,
            'event_id' => $order->event_id,
            'created_at' => now(),
        ]);
    }
}
