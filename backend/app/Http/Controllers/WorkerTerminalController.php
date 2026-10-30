<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Enums\AccountType;
use App\Enums\EventWorkerRole;
use App\Exceptions\SecurityException;
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
        $user = $request->user();

        // Any active assignment holding one of the terminal's actions may open it; each role then sees only its own panel.
        // (Ticket checkers and food station workers do not hold 'view_relevant_orders'.)
        $assignment = null;
        $denied = null;
        foreach (['view_relevant_orders', 'scan_qr_tickets', 'mark_quantities_collected', 'record_cash_received', 'oversee_assigned_operations'] as $action) {
            try {
                $assignment = EventWorkerService::assertActiveAssignmentValid($user, $event, $action);
                break;
            } catch (SecurityException $e) {
                $denied = $e;
            }
        }
        if ($assignment === null) {
            abort(403, $denied?->getMessage() ?? 'You are not assigned to this event.');
        }

        // Active role defaults to assignment role or event_host
        $activeRole = $assignment->worker_role ?? EventWorkerRole::EVENT_SUPERVISOR;

        return view('dashboards.worker-terminal', [
            'event' => $event,
            'assignment' => $assignment,
            'workerRole' => $activeRole,
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
    public function scanTicket(Request $request, Event $event): JsonResponse
    {
        $request->validate([
            'ticket_code' => ['required', 'string', 'max:80'],
        ]);

        $user = $request->user();
        $code = trim((string) $request->input('ticket_code'));

        try {
            $assignment = EventWorkerService::assertActiveAssignmentValid($user, $event, 'scan_qr_tickets');
        } catch (SecurityException $e) {
            return response()->json(['success' => false, 'error' => $e->getMessage()], 403);
        }

        $isToken = str_starts_with($code, 'tok_');
        if (!$isToken && $assignment->worker_role !== EventWorkerRole::EVENT_SUPERVISOR) {
            return response()->json([
                'success' => false,
                'valid' => false,
                'error' => 'Scan the QR code on the ticket. A typed ticket code or order number needs a supervisor.',
            ], 403);
        }

        $order = Order::withoutGlobalScopes()->whereNull('deleted_at')
            ->where('event_id', $event->id)
            ->when($isToken,
                fn ($q) => $q->where('secure_ticket_token', $code),
                fn ($q) => $q->where(fn ($w) => $w->where('ticket_code', $code)->orWhere('order_number', $code)))
            ->first();

        if (!$order) {
            return response()->json([
                'success' => false,
                'valid' => false,
                'error' => 'No ticket for this event matches that code.',
            ], 404);
        }

        try {
            $scanResult = EventWorkerService::scanAndValidateTicket($user, $order, (string) $event->id, manual: !$isToken);
            return response()->json($scanResult);
        } catch (SecurityException $e) {
            return response()->json(['success' => false, 'error' => $e->getMessage()], 403);
        }
    }

    /**
     * Record cash received at the event (Payment Clerk). Goes through the cash ledger, which locks the order,
     * caps the amount at the outstanding balance, enforces the order's currency and writes the audit record.
     */
    public function recordCash(Request $request, Event $event, CashLedgerService $ledger): JsonResponse
    {
        $request->validate([
            'order_number' => ['required_without:order_id', 'nullable', 'string', 'max:40'],
            'order_id' => ['required_without:order_number', 'nullable', 'string'],
            'amount' => ['required', 'numeric', 'min:0.01'],
            'notes' => ['nullable', 'string', 'max:255'],
        ]);

        $user = $request->user();

        try {
            EventWorkerService::assertActiveAssignmentValid($user, $event, 'record_cash_received');
        } catch (SecurityException $e) {
            return response()->json(['success' => false, 'error' => $e->getMessage()], 403);
        }

        $order = Order::withoutGlobalScopes()
            ->where('event_id', $event->id)
            ->when($request->filled('order_id'),
                fn ($q) => $q->whereKey($request->input('order_id')),
                fn ($q) => $q->where('order_number', trim((string) $request->input('order_number'))))
            ->first();

        if (!$order) {
            return response()->json(['success' => false, 'error' => 'No order with that number for this event.'], 404);
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
            'amount_paid' => (float) $paidOrder->amount_paid,
            'balance' => round((float) $paidOrder->total_amount - (float) $paidOrder->amount_paid, 2),
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

        try {
            $assignment = EventWorkerService::assertActiveAssignmentValid($request->user(), $event, 'mark_quantities_collected');
        } catch (SecurityException $e) {
            return response()->json(['success' => false, 'error' => $e->getMessage()], 403);
        }

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
    public function collectFood(Request $request, Event $event, OrderOperationsService $operations): JsonResponse
    {
        $request->validate([
            'order_id' => ['required', 'string'],
            'order_item_id' => ['required', 'string'],
            'quantity' => ['required', 'integer', 'min:1', 'max:100'],
        ]);

        $user = $request->user();

        try {
            $assignment = EventWorkerService::assertActiveAssignmentValid($user, $event, 'mark_quantities_collected');
        } catch (SecurityException $e) {
            return response()->json(['success' => false, 'error' => $e->getMessage()], 403);
        }

        $order = Order::withoutGlobalScopes()->whereNull('deleted_at')
            ->where('event_id', $event->id)
            ->whereKey($request->input('order_id'))
            ->first();
        if (!$order) {
            return response()->json(['success' => false, 'error' => 'No such order for this event.'], 404);
        }

        $item = OrderItem::where('id', $request->input('order_item_id'))->where('order_id', $order->id)->first();
        if (!$item || $item->menu_item_id === null) {
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
            return response()->json(['success' => false, 'error' => $e->getMessage()], 403);
        } catch (\InvalidArgumentException $e) {
            return response()->json(['success' => false, 'error' => $e->getMessage()], 422);
        }

        return response()->json(['success' => true, 'order' => $this->foodView($result['order'], $station)]);
    }

    private function findEventOrder(Event $event, string $code): ?Order
    {
        $code = trim($code);

        return Order::withoutGlobalScopes()->whereNull('deleted_at')
            ->where('event_id', $event->id)
            ->where(fn ($q) => $q->where('secure_ticket_token', $code)
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
                ->filter(fn (OrderItem $item) => $item->menu_item_id !== null)
                ->map(fn (OrderItem $item) => [
                    'id' => $item->id,
                    'name' => $item->name,
                    'options' => $item->selected_options_summary,
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
            ->flatMap(fn (Order $o) => $o->items->filter(fn (OrderItem $i) => $i->menu_item_id !== null));

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
