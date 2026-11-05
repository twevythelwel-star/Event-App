<?php

namespace App\Services;

use App\Enums\AccountType;
use App\Models\CancellationRequest;
use App\Models\Event;
use App\Models\HostPaymentPolicy;
use App\Models\Order;
use App\Models\SecurityAuditLog;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class CancellationService
{
    public function statusFor(Order $order): array
    {
        $event = Event::withoutGlobalScopes()->find($order->event_id);
        $policy = HostPaymentPolicy::where('organizer_id', $order->organizer_id)->first();
        $start = $event?->event_date_iso ?: $event?->date_str;
        if ($start && $event?->start_time) $start .= ' '.$event->start_time;
        $hours = $event?->refund_policy_deadline_hours ?? $policy?->refund_policy_deadline_hours ?? 48;
        $notes = $event?->refund_policy_notes ?: $policy?->refund_policy_notes;
        $deadline = null;
        if ($start) {
            try {
                $deadline = \Illuminate\Support\Carbon::parse($start, $event?->timezone ?: config('app.timezone'))->subHours((int) $hours);
            } catch (\Throwable) {
                // If an event date cannot be interpreted, fail closed and don't promise cancellation eligibility.
            }
        }
        $foodHours = $event?->food_order_cancellation_deadline_hours ?? $policy?->food_order_cancellation_deadline_hours;
        $hasFood = $order->items()->whereNotNull('menu_item_id')->exists();
        if ($hasFood && $start && $foodHours !== null) {
            try {
                $foodDeadline = \Illuminate\Support\Carbon::parse($start, $event?->timezone ?: config('app.timezone'))->subHours((int) $foodHours);
                if ($deadline === null || $foodDeadline->lessThan($deadline)) $deadline = $foodDeadline;
            } catch (\Throwable) {
                // Keep the existing event-wide cutoff if food deadline data is malformed.
            }
        }
        $latest = CancellationRequest::where('order_id', $order->id)->latest('requested_at')->first();
        $pending = CancellationRequest::where('order_id', $order->id)->whereIn('status', ['pending', 'approved'])->exists();
        $outstandingPaid = max(0, (int) $order->amount_paid_minor - (int) $order->refunded_minor);
        $providerPaid = $outstandingPaid > 0 && \App\Models\Payment::where('order_id', $order->id)->where('status', \App\Models\Payment::SUCCEEDED)->exists();
        $refundMode = $event?->refund_policy_mode ?? $policy?->refund_policy_mode ?? 'manual_full_and_partial';
        $nonRefundableCategory = $event ? self::nonRefundableTicketCategory($event, $order) : null;
        $refundBlocked = $outstandingPaid > 0 && ($providerPaid || $refundMode === 'non_refundable' || $nonRefundableCategory !== null);
        $eligible = $deadline !== null && !$pending && !$refundBlocked && !in_array($order->status, ['cancelled', 'refunded', 'rejected', 'expired'], true)
            && (!$deadline || now()->lt($deadline));

        return [
            'eligible' => $eligible,
            'deadline' => $deadline?->toIso8601String(),
            'policyNotes' => $notes,
            'status' => $latest?->status,
            'requestId' => $latest?->id,
            'refundStatus' => (int) $order->refunded_minor >= (int) $order->amount_paid_minor && (int) $order->amount_paid_minor > 0
                ? 'refunded' : ((int) $order->refunded_minor > 0 ? 'partially_refunded' : 'not_started'),
            'refundEligibilityReason' => $providerPaid
                ? 'Provider-backed refunds are not supported by EventBite.'
                : ($refundMode === 'non_refundable' && $outstandingPaid > 0
                    ? 'This event is configured as non-refundable.'
                    : ($nonRefundableCategory !== null && $outstandingPaid > 0
                        ? "The {$nonRefundableCategory} ticket type is non-refundable."
                        : null)),
            'refundHistory' => CashLedgerService::refundEntries($order),
            'history' => CancellationRequest::where('order_id', $order->id)->oldest('requested_at')->get()->map(fn ($r) => [
                'id' => $r->id, 'status' => $r->status, 'customerReason' => $r->customer_reason,
                'reviewReason' => $r->review_reason, 'requestedAt' => $r->requested_at?->toIso8601String(),
                'reviewedAt' => $r->reviewed_at?->toIso8601String(),
            ])->values(),
        ];
    }

    public static function nonRefundableTicketCategory(Event $event, Order $order): ?string
    {
        $excluded = array_values(array_filter((array) ($event->non_refundable_ticket_categories ?? []), 'is_string'));
        if ($excluded === []) return null;
        $tiers = collect((array) ($event->ticket_tiers ?? []))->keyBy(fn ($tier) => (string) ($tier['id'] ?? ''));
        $items = $order->relationLoaded('items') ? $order->items : $order->items()->get();
        foreach ($items->whereNotNull('ticket_tier_id') as $item) {
            $tier = $tiers->get((string) $item->ticket_tier_id);
            $category = is_array($tier) ? (string) ($tier['category'] ?? '') : '';
            if ($category !== '' && in_array($category, $excluded, true)) return $category;
        }
        return null;
    }

    public function request(User $user, Order $order, ?string $reason): CancellationRequest
    {
        abort_unless((string) $order->user_id === (string) $user->id, 404);
        $status = $this->statusFor($order);
        if (!$status['eligible']) throw new \InvalidArgumentException('This order is outside its cancellation policy or already has an open request.');

        return DB::transaction(function () use ($user, $order, $reason) {
            $locked = Order::withoutGlobalScopes()->lockForUpdate()->findOrFail($order->id);
            $state = $this->statusFor($locked);
            if (!$state['eligible']) throw new \InvalidArgumentException('This order is outside its cancellation policy or already has an open request.');
            $request = CancellationRequest::create([
                'order_id' => $locked->id, 'organizer_id' => $locked->organizer_id, 'requested_by' => $user->id,
                'status' => 'pending', 'customer_reason' => $reason, 'requested_at' => now(),
            ]);
            SecurityAuditLog::create(['id' => (string) Str::uuid(), 'user_id' => $user->id,
                'actor_role' => $user->getActiveRole()->value, 'action' => 'cancellation_requested',
                'target_entity' => 'orders', 'target_id' => $locked->id, 'ip_address' => request()->ip() ?? '127.0.0.1',
                'user_agent' => request()->userAgent() ?? 'api', 'details' => 'Cancellation request '.$request->id.' submitted.']);
            return $request;
        });
    }

    public function review(User $actor, CancellationRequest $cancellation, string $decision, string $reason): CancellationRequest
    {
        if (!in_array($decision, ['approved', 'rejected'], true)) throw new \InvalidArgumentException('Invalid cancellation decision.');
        $order = Order::withoutGlobalScopes()->findOrFail($cancellation->order_id);
        $operations = app(OrderOperationsService::class);
        $canReview = $operations->capabilities($actor, $order)['refund']
            || ($actor->hasRole(AccountType::ADMINISTRATOR)
                && \App\Support\AdminAuthorization::organizerInScope($actor, $order->organizer_id)
                && \Illuminate\Support\Facades\Gate::forUser($actor)->allows(\App\Support\AdminAuthorization::PAYMENTS_VIEW));
        abort_unless($canReview, 403);

        return DB::transaction(function () use ($actor, $cancellation, $decision, $reason, $order) {
            $lockedOrder = Order::withoutGlobalScopes()->lockForUpdate()->findOrFail($order->id);
            $locked = CancellationRequest::lockForUpdate()->findOrFail($cancellation->id);
            if ($locked->status !== 'pending') throw new \InvalidArgumentException('This cancellation request has already been reviewed.');
            if ($decision === 'approved' && $lockedOrder->status === 'paid') {
                $payment = \App\Models\Payment::where('order_id', $lockedOrder->id)->where('status', \App\Models\Payment::SUCCEEDED)->exists();
                if ($payment) throw new \InvalidArgumentException('Provider-backed refunds are unavailable until a verified provider refund integration is configured.');
            }
            $locked->status = $decision;
            $locked->reviewed_by = $actor->id;
            $locked->review_reason = $reason;
            $locked->reviewed_at = now();
            $locked->save();
            $stockDetail = '';
            if ($decision === 'approved' && (int) $lockedOrder->amount_paid_minor === 0) {
                $lockedOrder->status = 'cancelled';
                $lockedOrder->closed_at = now();
                $returned = app(\App\Services\Checkout\StockReturnService::class)->returnForRefund($lockedOrder);
                $stockDetail = " Returned {$returned['tickets']} ticket(s) and {$returned['portions']} portion(s) to stock.";
                $lockedOrder->save();
            }
            SecurityAuditLog::create(['id' => (string) Str::uuid(), 'user_id' => $actor->id,
                'actor_role' => $actor->getActiveRole()->value, 'action' => 'cancellation_'.$decision,
                'target_entity' => 'orders', 'target_id' => $lockedOrder->id, 'ip_address' => request()->ip() ?? '127.0.0.1',
                'user_agent' => request()->userAgent() ?? 'api', 'details' => "Cancellation request {$locked->id} {$decision}: {$reason}.{$stockDetail}"]); 
            return $locked->fresh();
        });
    }
}
