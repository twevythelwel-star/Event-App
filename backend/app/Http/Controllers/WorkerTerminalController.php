<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Enums\AccountType;
use App\Enums\EventWorkerRole;
use App\Exceptions\SecurityException;
use App\Models\Event;
use App\Models\EventWorkerAssignment;
use App\Models\Order;
use App\Services\EventWorkerService;
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
            $scanResult = EventWorkerService::scanAndValidateTicket($user, $order);
            return response()->json($scanResult);
        } catch (SecurityException $e) {
            return response()->json(['success' => false, 'error' => $e->getMessage()], 403);
        }
    }

    /**
     * Record cash received with mandatory audit trail for Payment Clerk.
     */
    public function recordCash(Request $request, Event $event): JsonResponse
    {
        $request->validate([
            'order_id' => ['required', 'string'],
            'amount_cents' => ['required', 'integer', 'min:1'],
            'notes' => ['nullable', 'string', 'max:255'],
        ]);

        $user = $request->user();

        try {
            EventWorkerService::assertActiveAssignmentValid($user, $event, 'record_cash_received');
        } catch (SecurityException $e) {
            return response()->json(['success' => false, 'error' => $e->getMessage()], 403);
        }

        $order = Order::where('id', $request->input('order_id'))
            ->where('event_id', $event->id)
            ->firstOrFail();

        try {
            $result = EventWorkerService::recordCashReceipt(
                worker: $user,
                order: $order,
                centsReceived: (int) $request->input('amount_cents'),
                receiptNotes: (string) ($request->input('notes') ?? '')
            );

            return response()->json($result);
        } catch (SecurityException $e) {
            return response()->json(['success' => false, 'error' => $e->getMessage()], 403);
        }
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
