<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Enums\AccountType;
use App\Enums\EventWorkerRole;
use App\Exceptions\SecurityException;
use App\Models\Event;
use App\Models\EventWorkerAssignment;
use App\Models\Order;
use App\Services\CashLedgerService;
use App\Services\EventWorkerService;
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

        try {
            $assignment = EventWorkerService::assertActiveAssignmentValid($user, $event, 'view_relevant_orders');
        } catch (SecurityException $e) {
            abort(403, $e->getMessage());
        }

        // Active role defaults to assignment role or event_host
        $activeRole = $assignment->worker_role ?? EventWorkerRole::EVENT_SUPERVISOR;

        return view('dashboards.worker-terminal', [
            'event' => $event,
            'assignment' => $assignment,
            'workerRole' => $activeRole,
            'station' => $assignment->station_assignment ?? 'all',
        ]);
    }

    /**
     * Server-side ticket validation endpoint for Ticket Checker.
     */
    public function scanTicket(Request $request, Event $event): JsonResponse
    {
        $request->validate([
            'ticket_code' => ['required', 'string'],
        ]);

        $user = $request->user();
        $ticketCode = trim((string) $request->input('ticket_code'));

        try {
            EventWorkerService::assertActiveAssignmentValid($user, $event, 'scan_qr_tickets');
        } catch (SecurityException $e) {
            return response()->json(['success' => false, 'error' => $e->getMessage()], 403);
        }

        $order = Order::where('event_id', $event->id)
            ->where(function ($q) use ($ticketCode) {
                $q->where('ticket_code', $ticketCode)
                  ->orWhere('secure_ticket_token', $ticketCode);
            })
            ->first();

        if (!$order) {
            return response()->json([
                'success' => false,
                'valid' => false,
                'error' => 'Invalid ticket code or ticket does not belong to this event.',
            ], 404);
        }

        try {
            $scanResult = EventWorkerService::scanAndValidateTicket($user, $order, (string) $event->id);
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
     * Partial and full food collection for Food Station Worker.
     */
    public function collectFood(Request $request, Event $event): JsonResponse
    {
        $request->validate([
            'order_id' => ['required', 'string'],
            'order_item_id' => ['required', 'string'],
            'quantity' => ['required', 'integer', 'min:1'],
        ]);

        $user = $request->user();

        try {
            EventWorkerService::assertActiveAssignmentValid($user, $event, 'mark_quantities_collected');
        } catch (SecurityException $e) {
            return response()->json(['success' => false, 'error' => $e->getMessage()], 403);
        }

        $order = Order::where('id', $request->input('order_id'))
            ->where('event_id', $event->id)
            ->firstOrFail();

        try {
            $result = EventWorkerService::markFoodCollected(
                worker: $user,
                order: $order,
                orderItemId: (string) $request->input('order_item_id'),
                qtyToCollect: (int) $request->input('quantity')
            );

            return response()->json($result);
        } catch (SecurityException $e) {
            return response()->json(['success' => false, 'error' => $e->getMessage()], 403);
        } catch (\InvalidArgumentException $e) {
            return response()->json(['success' => false, 'error' => $e->getMessage()], 422);
        }
    }
}
