<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Enums\AccountType;
use App\Enums\EventWorkerRole;
use App\Exceptions\SecurityException;
use App\Http\Requests\RecordCashPaymentRequest;
use App\Http\Requests\TicketVerificationRequest;
use App\Http\Requests\UpdateFulfillmentRequest;
use App\Models\Event;
use App\Models\EventWorkerAssignment;
use App\Models\Order;
use App\Models\OrderItem;
use App\Services\CashLedgerService;
use App\Services\EventWorkerService;
use App\Services\OrderOperationsService;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class WorkerTerminalController extends Controller
{
    /**
     * Display the dynamic worker terminal tailored to the active assignment.
     */
    public function index(Request $request, Event $event): View
    {
        // Assignment is validated by RequireActiveWorkerAssignment middleware
        $assignment = $request->attributes->get('worker_assignment');

        if (!$assignment) {
            abort(403, 'You are not assigned to this event.');
        }

        // A staff role with nothing to do at the terminal (e.g. a custom role with no permissions granted yet) does
        // not get the page; the event's host and managers always do.
        $terminalActions = ['scan_qr_tickets', 'record_cash_received', 'verify_payments', 'view_assigned_food_items', 'mark_quantities_collected', 'oversee_assigned_operations'];
        if (!$request->attributes->get('worker_runs_event')
            && !collect($terminalActions)->contains(fn (string $action) => $assignment->canPerform($action))) {
            abort(403, 'Your staff role has no terminal permissions for this event. Ask the event host to grant them.');
        }

        // Active role defaults to assignment role or event_host
        $activeRole = $assignment->worker_role ?? EventWorkerRole::EVENT_SUPERVISOR;

        return view('dashboards.worker-terminal', [
            'event' => $event,
            'assignment' => $assignment,
            'workerRole' => $activeRole,
            // The event's host and its organizer's managers run the event: every desk, whatever the staff role.
            'runsEvent' => (bool) $request->attributes->get('worker_runs_event'),
            'station' => $assignment->station_assignment ?? 'all',
            'stats' => $activeRole === EventWorkerRole::EVENT_SUPERVISOR ? $this->supervisorStats($event) : null,
        ]);
    }

    /**
     * Ticket check-in. The QR on a ticket carries its secret token, and that is what admits a guest.
     * A typed ticket code or order number is printed on receipts and staff screens, so it is not proof of a
     * ticket on its own: only a supervisor (or the event's host or a system administrator) may admit by it,
     * and that admission is audited as a manual check.
     */
    public function scanTicket(TicketVerificationRequest $request, Event $event): JsonResponse
    {
        $user = $request->user();
        $assignment = $request->attributes->get('worker_assignment');
        $code = trim((string) $request->input('ticket_code'));

        // Architecture A vs B guardrail: Public Event QR codes are promotional only and must NEVER admit anyone
        if (\App\Services\EventQr\EventQrService::isPromotionalEventQrPayload($code)) {
            $reason = 'Promotional Event QR scanned — DO NOT ADMIT. Public event QR codes only open the event page and cannot admit guests.';
            \App\Services\AuditLogService::log(
                action: 'ticket_redemption_rejected',
                targetEntity: 'events',
                targetId: (string) $event->id,
                details: "Public promotional event QR scanned at gate by {$user->email} ({$code}). Reason: {$reason}",
                actor: $user,
                organizerId: (string) $event->organizer_id,
                eventId: (string) $event->id
            );

            return response()->json([
                'success' => false,
                'valid' => false,
                'already_admitted' => false,
                'promotional_qr_rejected' => true,
                'reason_code' => 'promotional_qr',
                'rejection_reason' => $reason,
                'error' => $reason,
            ], 422);
        }

        $isToken = str_starts_with($code, 'tok_');
        if (!$isToken && $assignment->worker_role !== EventWorkerRole::EVENT_SUPERVISOR) {
            \App\Services\AuditLogService::log(
                action: 'ticket_redemption_rejected',
                targetEntity: 'events',
                targetId: (string) $event->id,
                details: "Manual non-QR code entry attempted by non-supervisor {$user->email} at event {$event->id}.",
                actor: $user,
                organizerId: (string) $event->organizer_id,
                eventId: (string) $event->id
            );

            return response()->json([
                'success' => false,
                'valid' => false,
                'reason_code' => 'unauthorized_manual_entry',
                'error' => 'Scan the QR code on the ticket. A typed ticket code or order number needs a supervisor.',
            ], 403);
        }

        $order = Order::withoutGlobalScopes()->whereNull('deleted_at')
            ->where('event_id', $event->id)
            ->when($isToken,
                fn ($q) => $q->whereTicketToken($code),
                fn ($q) => $q->where(fn ($w) => $w->where('ticket_code', $code)->orWhere('order_number', $code)))
            ->first();

        if (!$order) {
            \App\Services\AuditLogService::log(
                action: 'ticket_redemption_rejected',
                targetEntity: 'events',
                targetId: (string) $event->id,
                details: "Unrecognized, fake, or replaced ticket code/token rejected at gate by {$user->email}.",
                actor: $user,
                organizerId: (string) $event->organizer_id,
                eventId: (string) $event->id
            );

            return response()->json([
                'success' => false,
                'valid' => false,
                'reason_code' => 'invalid_ticket',
                'error' => 'No ticket for this event matches that code.',
            ], 404);
        }

        try {
            $scanResult = EventWorkerService::scanAndValidateTicket($user, $order, (string) $event->id,
                manual: !$isToken, requestId: $request->validated('scan_request_id'));
            return response()->json($scanResult);
        } catch (SecurityException $e) {
            return response()->json(['success' => false, 'reason_code' => 'unauthorized_scanner', 'error' => $e->getMessage()], 403);
        }
    }

    /**
     * Record cash received at the event (Payment Clerk). Goes through the cash ledger, which locks the order,
     * caps the amount at the outstanding balance, enforces the order's currency and writes the audit record.
     */
    public function recordCash(RecordCashPaymentRequest $request, Event $event, CashLedgerService $ledger): JsonResponse
    {
        $user = $request->user();
        $assignment = $request->attributes->get('worker_assignment');

        $order = Order::withoutGlobalScopes()
            ->where('event_id', $event->id)
            ->when($request->filled('order_id'),
                fn ($q) => $q->whereKey($request->input('order_id')),
                fn ($q) => $q->where('order_number', trim((string) $request->input('order_number'))))
            ->first();

        if (!$order) {
            return response()->json(['success' => false, 'error' => 'No order with that number for this event.'], 404);
        }

        // Policy check
        if (!$user->can('recordCashPayment', [$order, $assignment])) {
            return response()->json(['success' => false, 'error' => 'Permission denied.'], 403);
        }

        try {
            $result = $ledger->recordReceipt($user, [
                'order_id' => $order->id,
                'amount' => $request->input('amount'),
                'notes' => $request->input('notes'),
            ]);
        } catch (SecurityException $e) {
            return response()->json(['success' => false, 'error' => $e->getMessage()], 403);
        } catch (ModelNotFoundException) {
            return response()->json(['success' => false, 'error' => 'No order with that number for this event.'], 404);
        } catch (\InvalidArgumentException $e) {
            return response()->json(['success' => false, 'error' => $e->getMessage()], 422);
        }

        $paidOrder = $result['order'];

        return response()->json([
            'success' => true,
            'receipt_number' => $result['receipt']->receipt_number,
            'order_number' => $paidOrder->order_number,
            'currency' => $paidOrder->currency,
            'amount_paid' => $paidOrder->money('amount_paid_minor')->toMajorNumber(),
            'balance' => $paidOrder->money('total_minor')->minus($paidOrder->money('amount_paid_minor'))->max(\App\Support\Money::zero($paidOrder->currency))->toMajorNumber(),
            'amount_paid_minor' => (int) $paidOrder->amount_paid_minor,
            'balance_minor' => $paidOrder->balanceMinor(),
            'status' => $paidOrder->status,
        ]);
    }

    /**
     * Food station lookup: find an order at this event by ticket code, QR token or order number,
     * and list its food lines with what is left to hand over. No prices or payment details.
     */
    public function lookupOrder(Request $request, Event $event): JsonResponse
    {
        $request->validate(['code' => ['required', 'string', 'max:80']]);

        $assignment = $request->attributes->get('worker_assignment');

        $order = $this->findEventOrder($event, (string) $request->input('code'));
        if (!$order) {
            return response()->json(['success' => false, 'error' => 'No order with that code for this event.'], 404);
        }

        return response()->json(['success' => true, 'order' => $this->foodView($order, $assignment->station_assignment ?? 'all')]);
    }

    /**
     * Partial and full food collection for Food Station Worker. Goes through the same handover service as the
     * host screens: the item row is locked, quantities are capped, unpaid orders are refused and every handover
     * is written to the handover audit log.
     */
    public function collectFood(UpdateFulfillmentRequest $request, Event $event, OrderOperationsService $operations): JsonResponse
    {
        $user = $request->user();
        $assignment = $request->attributes->get('worker_assignment');

        $order = Order::withoutGlobalScopes()->whereNull('deleted_at')
            ->where('event_id', $event->id)
            ->whereKey($request->input('order_id'))
            ->first();
        if (!$order) {
            return response()->json(['success' => false, 'error' => 'No such order for this event.'], 404);
        }

        $item = OrderItem::where('id', $request->input('order_item_id'))->where('order_id', $order->id)->first();
        if (!$item || !$item->isHandoverItem()) {
            return response()->json(['success' => false, 'error' => 'That food item is not on this order.'], 422);
        }

        // A worker assigned to one station only hands over that station's items (station = item category)
        $station = $assignment->station_assignment ?? 'all';
        if (!$this->servesAt($item, $station)) {
            return response()->json(['success' => false, 'error' => "This item is not served at the {$station} station."], 403);
        }

        try {
            $result = $operations->recordHandover($user, $order, $item->id, (int) $request->input('quantity'), [
                'action_type' => 'collection',
                'station' => $station,
            ]);
        } catch (SecurityException $e) {
            \App\Services\AuditLogService::log(
                action: 'food_handover_rejected',
                targetEntity: 'orders',
                targetId: (string) $order->id,
                details: "Food handover denied for item {$item->id} on order {$order->order_number} by {$user->email}: {$e->getMessage()}",
                actor: $user,
                organizerId: (string) $event->organizer_id,
                eventId: (string) $event->id,
                outcome: 'denied'
            );
            return response()->json(['success' => false, 'error' => $e->getMessage()], 403);
        } catch (\InvalidArgumentException $e) {
            // Includes over-quantity attempts and unpaid-order handovers: a fraud signal worth auditing.
            \App\Services\AuditLogService::log(
                action: 'food_handover_rejected',
                targetEntity: 'orders',
                targetId: (string) $order->id,
                details: "Food handover rejected for item {$item->id} on order {$order->order_number} by {$user->email}: {$e->getMessage()}",
                actor: $user,
                organizerId: (string) $event->organizer_id,
                eventId: (string) $event->id,
                outcome: 'rejected'
            );
            return response()->json(['success' => false, 'error' => $e->getMessage()], 422);
        }

        return response()->json(['success' => true, 'order' => $this->foodView($result['order'], $station)]);
    }

    private function findEventOrder(Event $event, string $code): ?Order
    {
        $code = trim($code);

        return Order::withoutGlobalScopes()->whereNull('deleted_at')
            ->where('event_id', $event->id)
            ->where(fn ($q) => $q->whereTicketToken($code)
                ->orWhere('ticket_code', $code)
                ->orWhere('order_number', $code))
            ->with('items')
            ->first();
    }

    /** What a food station may see of an order: its number, first name, payment state and food lines. */
    private function foodView(Order $order, string $station): array
    {
        $order->loadMissing('items');

        return [
            'id' => $order->id,
            'order_number' => $order->order_number,
            'customer_first_name' => strtok((string) $order->customer_name, ' ') ?: 'Guest',
            'payment_verified' => $order->isEligibleForHandover(),
            'items' => $order->items
                // Package lines are shown through their items, each handed over at its own station.
                ->filter(fn (OrderItem $item) => $item->isHandoverItem())
                ->map(fn (OrderItem $item) => [
                    'id' => $item->id,
                    'name' => $item->name,
                    'options' => $item->selected_options_summary,
                    'part_of' => $item->line_type === OrderItem::COMPONENT ? ($item->selected_options_summary[0] ?? null) : null,
                    'preparation_status' => $item->preparation_status ?? 'pending',
                    'payment_status' => $item->payment_status,
                    'quantity' => (int) $item->quantity,
                    'collected' => (int) $item->collected_quantity,
                    'remaining' => max(0, (int) $item->quantity - (int) $item->collected_quantity),
                    'category' => $item->category,
                    'at_this_station' => $this->servesAt($item, $station),
                ])
                ->values()
                ->all(),
        ];
    }

    /** Same rule as EventWorkerRole::sanitizeOrderForWorker: a station name matches the item category. */
    private function servesAt(OrderItem $item, string $station): bool
    {
        return $station === 'all' || strcasecmp((string) $item->category, $station) === 0;
    }

    /**
     * Live figures for the supervisor desk: tickets admitted, food handed over, and staff on shift.
     *
     * @return array{tickets_admitted: int, tickets_valid: int, portions_collected: int, portions_ordered: int, staff_on_shift: int}
     */
    private function supervisorStats(Event $event): array
    {
        $orders = Order::withoutGlobalScopes()->whereNull('deleted_at')
            ->where('event_id', $event->id)
            ->whereNotIn('status', ['refunded', 'rejected', 'cancelled', 'expired'])
            ->with('items')
            ->get();

        $ticketOrders = $orders->filter(fn (Order $o) => $o->items->contains(fn (OrderItem $i) => $i->ticket_tier_id !== null));
        $foodItems = $orders->filter(fn (Order $o) => $o->isEligibleForHandover())
            ->flatMap(fn (Order $o) => $o->items->filter(fn (OrderItem $i) => $i->isHandoverItem()));

        return [
            'tickets_admitted' => $ticketOrders->whereNotNull('checked_in_at')->count(),
            'tickets_valid' => $ticketOrders->filter(fn (Order $o) => in_array($o->status, ['paid', 'confirmed'], true))->count(),
            'portions_collected' => (int) $foodItems->sum('collected_quantity'),
            'portions_ordered' => (int) $foodItems->sum('quantity'),
            'staff_on_shift' => EventWorkerAssignment::withoutGlobalScopes()
                ->where('event_id', $event->id)
                ->where('is_active', true)
                ->get()
                ->filter(fn (EventWorkerAssignment $a) => $a->isCurrentlyActive())
                ->count(),
        ];
    }
}
