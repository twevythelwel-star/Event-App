<?php

namespace App\Services;

use App\Enums\AccountType;
use App\Exceptions\SecurityException;
use App\Models\Order;
use App\Models\SecurityAuditLog;
use App\Models\TicketDispute;
use App\Models\User;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Support\Str;

/**
 * Attendee disputes about their own orders, investigated and closed by platform administrators.
 *
 * Delegated administrators follow the same scoping as the admin dashboard: no permission row means
 * platform-wide access; a scope other than 'all' limits them to their assigned organizers; and
 * can_support_orders = false removes dispute handling entirely. Resolving a dispute never moves money:
 * refunds stay with the event owner's refund flow.
 */
class DisputeService
{
    /**
     * @param array{order_id: string, dispute_type?: string|null, reason: string, amount?: float|int|string|null} $data
     */
    public function submit(User $user, array $data): TicketDispute
    {
        $order = Order::withoutGlobalScopes()->with('event')->whereKey($data['order_id'])->where('user_id', $user->id)->first();
        if (!$order) {
            throw (new ModelNotFoundException('Order not found.'))->setModel(Order::class);
        }

        $reason = trim((string) $data['reason']);
        if (mb_strlen($reason) < 10) {
            throw new \InvalidArgumentException('Please describe the problem in at least 10 characters.');
        }

        $type = $data['dispute_type'] ?? 'ticket_refund';
        if (!in_array($type, TicketDispute::TYPES, true)) {
            throw new \InvalidArgumentException("Unsupported dispute type '{$type}'.");
        }

        try {
            $amount = \App\Support\Money::parse($data['amount'] ?? 0, $order->currency);
        } catch (\InvalidArgumentException) {
            throw new \InvalidArgumentException('Enter the disputed amount as a number, e.g. 25.00.');
        }
        if ($amount->isNegative() || $amount->minor() > (int) $order->total_minor) {
            throw new \InvalidArgumentException('The disputed amount cannot exceed the order total.');
        }

        $alreadyOpen = TicketDispute::where('order_id', $order->id)->whereIn('status', TicketDispute::OPEN_STATUSES)->exists();
        if ($alreadyOpen) {
            throw new \InvalidArgumentException('This order already has an open dispute under review.');
        }

        $dispute = TicketDispute::create([
            'dispute_number' => $this->nextDisputeNumber(),
            'organizer_id' => $order->organizer_id,
            'event_id' => $order->event_id,
            'order_id' => $order->id,
            'user_id' => $user->id,
            'dispute_type' => $type,
            'status' => 'open',
            'amount_minor' => $amount->minor(),
            'currency' => $order->currency,
            'reason' => $reason,
        ]);

        $this->audit($user, 'dispute_submitted', $dispute, "Dispute {$dispute->dispute_number} filed on order {$order->order_number}.");
        if ($type === 'ticket_refund') {
            \App\Services\Notifications\NotificationService::notifyOrder($order, 'refund_requested', ['reference' => $dispute->dispute_number], (string) $dispute->id);
        }

        return $dispute->fresh(['order', 'organizer', 'event']);
    }

    public function mine(User $user): Builder
    {
        return TicketDispute::with(['order', 'organizer', 'event'])->where('user_id', $user->id)->latest();
    }

    /**
     * Disputes this administrator may work on.
     */
    public function adminQuery(User $admin): Builder
    {
        $query = TicketDispute::with(['order', 'organizer', 'event'])->latest();

        if ($admin->hasRole(AccountType::SYSTEM_ADMIN)) {
            return $query;
        }
        if (!$admin->hasRole(AccountType::ADMINISTRATOR)) {
            return $query->whereRaw('1 = 0');
        }

        $permissions = $admin->adminPermissions;
        if ($permissions && $permissions->can_support_orders === false) {
            return $query->whereRaw('1 = 0');
        }
        if ($permissions && $permissions->scope !== 'all') {
            $query->whereIn('organizer_id', $permissions->assigned_organizer_ids ?? []);
        }

        return $query;
    }

    /**
     * @param 'under_review'|'resolved'|'rejected' $status
     */
    public function updateStatus(User $admin, string $disputeId, string $status, ?string $notes): TicketDispute
    {
        if (!$admin->hasRole(AccountType::ADMINISTRATOR) && !$admin->hasRole(AccountType::SYSTEM_ADMIN)) {
            throw new SecurityException('Unauthorized: only administrators can work on disputes.');
        }

        $dispute = $this->adminQuery($admin)->whereKey($disputeId)->first();
        if (!$dispute) {
            throw (new ModelNotFoundException('Dispute not found.'))->setModel(TicketDispute::class);
        }
        if (!$dispute->isOpen()) {
            throw new \InvalidArgumentException("This dispute is already {$dispute->status}.");
        }
        if (!in_array($status, ['under_review', 'resolved', 'rejected'], true)) {
            throw new \InvalidArgumentException("Unsupported dispute status '{$status}'.");
        }

        $notes = trim((string) $notes);
        if ($status !== 'under_review' && $notes === '') {
            throw new \InvalidArgumentException('Resolution notes are required to close a dispute.');
        }

        $dispute->status = $status;
        $dispute->assigned_admin_id = $admin->id;
        $dispute->assigned_admin_name = $admin->name;
        if ($notes !== '') {
            $dispute->resolution_notes = $notes;
        }
        if ($status !== 'under_review') {
            $dispute->resolved_at = now();
        }
        $dispute->save();

        $this->audit($admin, "dispute_{$status}", $dispute, "Dispute {$dispute->dispute_number} marked {$status}" . ($notes !== '' ? ": {$notes}" : '.'));

        return $dispute->fresh(['order', 'organizer', 'event']);
    }

    /**
     * Dispute in the SPA's TicketDispute shape.
     */
    public static function present(TicketDispute $dispute): array
    {
        return [
            'id' => $dispute->id,
            'disputeNumber' => $dispute->dispute_number,
            'orderId' => $dispute->order_id,
            'orderNumber' => $dispute->order?->order_number,
            'ticketCode' => $dispute->order?->ticket_code,
            'customerName' => $dispute->order?->customer_name,
            'customerPhone' => $dispute->order?->customer_phone,
            'organizerId' => $dispute->organizer_id,
            'organizerName' => $dispute->organizer?->name,
            'eventName' => $dispute->event?->title,
            'disputeType' => $dispute->dispute_type,
            'status' => $dispute->status,
            'amount' => $dispute->money('amount_minor')->toMajorNumber(),
            'amountMinor' => (int) $dispute->amount_minor,
            'currency' => $dispute->currency,
            'reason' => $dispute->reason,
            'createdAt' => $dispute->created_at?->toIso8601String(),
            'updatedAt' => $dispute->updated_at?->toIso8601String(),
            'assignedAdminName' => $dispute->assigned_admin_name,
            'resolutionNotes' => $dispute->resolution_notes,
        ];
    }

    private function nextDisputeNumber(): string
    {
        $year = date('Y');
        do {
            $number = "DSP-{$year}-" . random_int(100000, 999999);
        } while (TicketDispute::where('dispute_number', $number)->exists());

        return $number;
    }

    private function audit(User $user, string $action, TicketDispute $dispute, string $details): void
    {
        SecurityAuditLog::create([
            'id' => (string) Str::uuid(),
            'user_id' => $user->id,
            'actor_role' => $user->getActiveRole()->value,
            'action' => $action,
            'target_entity' => 'ticket_disputes',
            'target_id' => $dispute->id,
            'ip_address' => request()->ip() ?? '127.0.0.1',
            'user_agent' => request()->userAgent() ?? 'api',
            'details' => $details,
            'is_pii_access' => false,
            'created_at' => now(),
        ]);
    }
}
