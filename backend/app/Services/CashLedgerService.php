<?php

namespace App\Services;

use App\Enums\AccountType;
use App\Exceptions\SecurityException;
use App\Models\CashPayment;
use App\Models\Event;
use App\Models\Order;
use App\Models\SecurityAuditLog;
use App\Models\User;
use App\Services\Checkout\StockReturnService;
use App\Services\Notifications\NotificationService;
use App\Support\Money;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

/**
 * Cash receipts, cash reversals and order refunds, with the same per-organizer/per-event access rules as
 * OrderOperationsService. Money movements are append-only: a mistake is corrected by a linked reversal row,
 * never by editing or deleting the original receipt.
 */
class CashLedgerService
{
    public function __construct(
        protected OrderOperationsService $operations,
        protected StockReturnService $stock
    ) {}

    /**
     * Ledger rows visible to the user: organizers where they may handle cash, or events they are assigned to as cashier.
     */
    public function ledgerQuery(User $user): Builder
    {
        $query = CashPayment::withoutGlobalScopes()->latest('received_at')->latest();

        if ($user->hasRole(AccountType::SYSTEM_ADMIN)) {
            return $query;
        }

        $reach = $this->operations->reach($user);
        $organizers = $reach['organizers']->filter(fn ($id) => $this->operations->capabilitiesFor($user, $id, null)['cash']);
        $events = Event::withoutGlobalScopes()->whereIn('id', $reach['events'])->get()
            ->filter(fn (Event $event) => $this->operations->capabilitiesFor($user, $event->organizer_id, $event->id)['cash'])
            ->pluck('id');

        return $query->where(function (Builder $q) use ($organizers, $events) {
            $q->whereIn('organizer_id', $organizers)->orWhereIn('event_id', $events);
        });
    }

    /**
     * @param array{order_id?: string|null, event_id?: string|null, customer_name?: string|null, customer_phone?: string|null,
     *              amount: float|int|string, currency?: string|null, received_at?: string|null, received_from?: string|null, notes?: string|null} $data
     * @return array{receipt: CashPayment, order: ?Order}
     */
    public function recordReceipt(User $user, array $data): array
    {
        $order = null;
        if (!empty($data['order_id'])) {
            $order = $this->operations->operableOrdersQuery($user)->whereKey($data['order_id'])->first();
            if (!$order) {
                throw (new ModelNotFoundException('Order not found.'))->setModel(Order::class);
            }
            $event = Event::withoutGlobalScopes()->find($order->event_id);
        } else {
            $event = !empty($data['event_id']) ? Event::withoutGlobalScopes()->find($data['event_id']) : null;
            if (!$event) {
                throw new \InvalidArgumentException('Choose the event this cash was received for.');
            }
        }

        $organizerId = $order?->organizer_id ?? $event->organizer_id;
        if (!$this->operations->capabilitiesFor($user, $organizerId, $event?->id)['cash']) {
            throw new SecurityException('Unauthorized: you are not permitted to record cash for this event.');
        }

        $currency = $order?->currency ?? ($data['currency'] ?? $event->currency ?? \App\Support\Currency::defaultCode());
        try {
            $amount = Money::parse($data['amount'], $currency);
        } catch (\InvalidArgumentException) {
            throw new \InvalidArgumentException('Enter the cash amount as a number, e.g. 25.00.');
        }
        if (!$amount->isPositive()) {
            throw new \InvalidArgumentException('Cash amount must be greater than zero.');
        }

        if ($order && !empty($data['currency']) && $data['currency'] !== $order->currency) {
            throw new \InvalidArgumentException("This order is priced in {$order->currency}; record the cash in {$order->currency}.");
        }

        if ($order) {
            if (in_array($order->status, ['refunded', 'rejected'], true)) {
                throw new \InvalidArgumentException("Cash cannot be recorded against a {$order->status} order.");
            }
            $balance = Money::ofMinor($order->balanceMinor(), $currency);
            if ($amount->compareTo($balance) > 0) {
                throw new \InvalidArgumentException('Amount exceeds the outstanding balance of ' . $balance->display() . '.');
            }
        }

        $customerName = trim((string) ($order?->customer_name ?? $data['customer_name'] ?? ''));
        $customerPhone = trim((string) ($order?->customer_phone ?? $data['customer_phone'] ?? ''));
        if ($customerName === '' || $customerPhone === '') {
            throw new \InvalidArgumentException('Customer name and phone are required for a walk-up cash receipt.');
        }

        // The ledger names the signed-in account; a different person named on the form is kept in the notes
        $notes = trim((string) ($data['notes'] ?? ''));
        $receivedFrom = trim((string) ($data['received_from'] ?? ''));
        if ($receivedFrom !== '' && $receivedFrom !== $user->name) {
            $notes = trim("Handed to: {$receivedFrom}. {$notes}");
        }

        return DB::transaction(function () use ($user, $order, $event, $organizerId, $amount, $currency, $customerName, $customerPhone, $notes, $data) {
            $receipt = CashPayment::create([
                'organizer_id' => $organizerId,
                'event_id' => $event?->id,
                'order_id' => $order?->id,
                'transaction_type' => 'receipt',
                'receipt_number' => $this->nextReceiptNumber('CSH'),
                'customer_name' => $customerName,
                'customer_phone' => $customerPhone,
                'event_name' => $event?->title,
                'order_number' => $order?->order_number,
                'ticket_code' => $order?->ticket_code,
                'amount_minor' => $amount->minor(),
                'currency' => $currency,
                'received_at' => !empty($data['received_at']) ? $data['received_at'] : now(),
                'received_by' => $user->name,
                'notes' => $notes ?: null,
            ]);

            if ($order) {
                $order = Order::withoutGlobalScopes()->lockForUpdate()->find($order->id);
                $paid = (int) $order->amount_paid_minor + $amount->minor();
                $wasPaid = $order->status === 'paid';
                $order->amount_paid_minor = $paid;
                $order->status = $paid >= (int) $order->total_minor ? 'paid' : 'partially_paid';
                $order->verified_at = now();
                $order->verified_by = $user->name;
                $order->verification_notes = "Cash receipt {$receipt->receipt_number} recorded by {$user->name}.";
                $order->save();

                NotificationService::notifyOrder($order, 'cash_receipt_recorded', ['amount' => $amount->toDecimalString(), 'receipt' => $receipt->receipt_number], $receipt->id);
                if (!$wasPaid && $order->status === 'paid') {
                    NotificationService::paymentSettled($order);
                }
            }

            $this->audit($user, 'cash_receipt_recorded', $receipt->id, "Cash receipt {$receipt->receipt_number}: {$amount->display()} ({$currency})" . ($order ? " for order {$order->order_number}" : ' (walk-up)') . '.');

            return ['receipt' => $receipt, 'order' => $order?->fresh(['items', 'event'])];
        });
    }

    /**
     * @return array{reversal: CashPayment, order: ?Order}
     */
    public function reverseReceipt(User $user, string $receiptId, float|int|string $amount, string $reason): array
    {
        $receipt = $this->ledgerQuery($user)->whereKey($receiptId)->first();
        if (!$receipt) {
            throw (new ModelNotFoundException('Cash receipt not found.'))->setModel(CashPayment::class);
        }
        if (!$this->operations->capabilitiesFor($user, $receipt->organizer_id, $receipt->event_id)['reverse']) {
            throw new SecurityException('Unauthorized: only the event owner can reverse cash entries.');
        }
        if ($receipt->transaction_type === 'reversal') {
            throw new \InvalidArgumentException('A reversal cannot itself be reversed; record a new receipt instead.');
        }

        $reason = trim($reason);
        if ($reason === '') {
            throw new \InvalidArgumentException('A reason is required for a cash reversal.');
        }

        $amount = Money::parse($amount, $receipt->currency);
        $alreadyReversed = abs((int) CashPayment::withoutGlobalScopes()->where('original_record_id', $receipt->id)->sum('amount_minor'));
        $reversible = (int) $receipt->amount_minor - $alreadyReversed;
        if (!$amount->isPositive() || $amount->minor() > $reversible) {
            throw new \InvalidArgumentException("Reversal must be more than zero and at most " . Money::formatMinor(max(0, $reversible), $receipt->currency) . " ({$receipt->currency}).");
        }

        return DB::transaction(function () use ($user, $receipt, $amount, $reason) {
            $reversal = CashPayment::create([
                'organizer_id' => $receipt->organizer_id,
                'event_id' => $receipt->event_id,
                'order_id' => $receipt->order_id,
                'transaction_type' => 'reversal',
                'receipt_number' => $this->nextReceiptNumber('REV'),
                'customer_name' => $receipt->customer_name,
                'customer_phone' => $receipt->customer_phone,
                'event_name' => $receipt->event_name,
                'order_number' => $receipt->order_number,
                'ticket_code' => $receipt->ticket_code,
                'amount_minor' => -$amount->minor(),
                'currency' => $receipt->currency,
                'received_at' => now(),
                'received_by' => $receipt->received_by,
                'notes' => "Reversal of {$receipt->receipt_number}: {$reason}",
                'original_record_id' => $receipt->id,
                'reversal_of_id' => $receipt->id,
                'reversal_reason' => $reason,
                'reversal_authorized_by' => $user->name,
            ]);

            $order = null;
            if ($receipt->order_id) {
                $order = Order::withoutGlobalScopes()->lockForUpdate()->find($receipt->order_id);
                if ($order) {
                    $paid = max(0, (int) $order->amount_paid_minor - $amount->minor());
                    $order->amount_paid_minor = $paid;
                    if ($order->status !== 'refunded') {
                        $order->status = $paid <= 0 ? 'awaiting_payment' : ($paid >= (int) $order->total_minor ? 'paid' : 'partially_paid');
                    }
                    $order->verification_notes = "Reversal {$reversal->receipt_number} applied by {$user->name}: {$reason}";
                    $order->save();
                }
            }

            $this->audit($user, 'cash_receipt_reversed', $reversal->id, "Reversal {$reversal->receipt_number} of {$receipt->receipt_number}: {$amount->display()} ({$receipt->currency}). Reason: {$reason}");

            return ['reversal' => $reversal, 'order' => $order?->fresh(['items', 'event'])];
        });
    }

    /**
     * Record a refund paid back to the customer. Partial refunds keep the order's status; refunding everything
     * received marks the order refunded (which also blocks further handover).
     */
    public function refundOrder(User $user, string $orderId, float|int|string $amount, string $reason): Order
    {
        $order = $this->operations->operableOrdersQuery($user)->whereKey($orderId)->first();
        if (!$order) {
            throw (new ModelNotFoundException('Order not found.'))->setModel(Order::class);
        }
        if (!$this->operations->capabilities($user, $order)['refund']) {
            throw new SecurityException('Unauthorized: only the event owner can issue refunds.');
        }

        $reason = trim($reason);
        if ($reason === '') {
            throw new \InvalidArgumentException('A reason is required for a refund.');
        }

        try {
            $amount = Money::parse($amount, $order->currency);
        } catch (\InvalidArgumentException) {
            throw new \InvalidArgumentException('Enter the refund amount as a number, e.g. 25.00.');
        }

        // The refundable amount is read from the order row locked for update, so two refunds sent at the same
        // moment cannot both pass the check against the same, stale history.
        return DB::transaction(function () use ($user, $order, $amount, $reason) {
            $order = Order::withoutGlobalScopes()->lockForUpdate()->find($order->id);

            $refundable = Money::ofMinor((int) $order->amount_paid_minor - (int) $order->refunded_minor, $order->currency);
            if (!$amount->isPositive() || $amount->compareTo($refundable) > 0) {
                throw new \InvalidArgumentException("Refund must be more than zero and at most " . $refundable->max(Money::zero($order->currency))->display() . " ({$order->currency}), the amount received and not yet refunded.");
            }

            // The refund is its own auditable row; refund_details is kept in step for older readers.
            $adjustment = \App\Models\OrderAdjustment::create([
                'order_id' => $order->id,
                'organizer_id' => $order->organizer_id,
                'type' => \App\Models\OrderAdjustment::TYPE_REFUND,
                'amount_minor' => $amount->minor(),
                'currency' => $order->currency,
                'reason' => $reason,
                'actor_user_id' => $user->id,
                'actor_name' => $user->name,
                'occurred_at' => now(),
            ]);
            $order->refunded_minor = (int) $order->refunded_minor + $amount->minor();

            $refunds = self::refundEntries($order);
            $refunds[] = [
                'amount' => $amount->toDecimalString(),
                'reason' => $reason,
                'refundedAt' => now()->toIso8601String(),
                'refundedBy' => $user->name,
            ];
            $order->refund_details = $refunds;

            $stockNote = '';
            if ((int) $order->refunded_minor >= (int) $order->amount_paid_minor && $order->status !== 'refunded') {
                $order->status = 'refunded';
                // Fully refunded: unused tickets and portions go back on sale (partial refunds return nothing).
                $returned = $this->stock->returnForRefund($order);
                $stockNote = " Returned to stock: {$returned['tickets']} ticket(s), {$returned['portions']} portion(s).";
            }
            $order->save();

            $this->audit($user, 'order_refunded', $order->id, "Refund of {$amount->display()} ({$order->currency}) on order {$order->order_number}. Reason: {$reason}.{$stockNote}", 'orders');
            NotificationService::notifyOrder($order, 'refund_completed', ['amount' => $amount->toDecimalString()], (string) $adjustment->id);

            return $order->fresh(['items', 'event']);
        });
    }

    /**
     * Refund history as a list of {amount, reason, refundedAt, refundedBy}, tolerating the legacy gateway format.
     *
     * Amounts are read exactly (never through a float); order_adjustments is the authoritative record.
     *
     * @return array<int, array{amount: float, amount_minor: int, reason: string, refundedAt: string, refundedBy: string}>
     */
    public static function refundEntries(Order $order): array
    {
        $money = static function (array $entry) use ($order): Money {
            try {
                return isset($entry['amount_minor'])
                    ? Money::ofMinor((int) $entry['amount_minor'], $order->currency)
                    : Money::parse($entry['amount'] ?? 0, $order->currency);
            } catch (\InvalidArgumentException) {
                return Money::zero($order->currency);
            }
        };

        return array_map(fn (array $entry) => [
            'amount' => $money($entry)->toMajorNumber(),
            'amount_minor' => $money($entry)->minor(),
            'reason' => (string) ($entry['reason'] ?? ''),
            'refundedAt' => (string) ($entry['refundedAt'] ?? $entry['timestamp'] ?? ''),
            'refundedBy' => (string) ($entry['refundedBy'] ?? $entry['authorized_by'] ?? ''),
        ], array_values(array_filter((array) ($order->refund_details ?? []), 'is_array')));
    }

    private function nextReceiptNumber(string $prefix): string
    {
        $year = date('Y');
        do {
            $number = "{$prefix}-{$year}-" . random_int(100000, 999999);
        } while (CashPayment::withoutGlobalScopes()->where('receipt_number', $number)->exists());

        return $number;
    }

    private function audit(User $user, string $action, string $targetId, string $details, string $entity = 'cash_payments'): void
    {
        SecurityAuditLog::create([
            'id' => (string) Str::uuid(),
            'user_id' => $user->id,
            'actor_role' => $user->getActiveRole()->value,
            'action' => $action,
            'target_entity' => $entity,
            'target_id' => $targetId,
            'ip_address' => request()->ip() ?? '127.0.0.1',
            'user_agent' => request()->userAgent() ?? 'api',
            'details' => $details,
            'is_pii_access' => false,
            'created_at' => now(),
        ]);
    }
}
