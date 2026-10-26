<?php

namespace App\Http\Controllers\Api;

use App\Exceptions\SecurityException;
use App\Http\Controllers\Controller;
use App\Http\Resources\OrderResource;
use App\Models\CashPayment;
use App\Models\HandoverAuditLog;
use App\Models\Order;
use App\Models\User;
use App\Services\CashLedgerService;
use App\Services\OrderOperationsService;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

/**
 * Host order verification, cash ledger, refunds and event-day staff scanner endpoints for the single-page app.
 */
class OrderOperationsController extends Controller
{
    /** Payment fields withheld from staff who cannot verify payments (e.g. ticket checkers, food stations). */
    private const PAYMENT_FIELDS = ['paymentReference', 'senderName', 'email', 'verifiedBy', 'rejectionReason', 'refunds'];

    public function __construct(
        protected OrderOperationsService $operations,
        protected CashLedgerService $cash
    ) {}

    public function index(Request $request): JsonResponse
    {
        $user = $request->user();
        $orders = $this->operations->operableOrdersQuery($user)->limit(500)->get();

        return response()->json([
            'data' => $orders->map(fn (Order $order) => $this->present($user, $order, $request))->values(),
        ]);
    }

    public function lookup(Request $request): JsonResponse
    {
        $data = $request->validate(['code' => ['required', 'string', 'max:100']]);
        $order = $this->operations->findByCode($request->user(), $data['code']);

        // Orders the user cannot operate on look identical to unknown codes
        if (!$order || !$this->operations->capabilities($request->user(), $order)['scan']) {
            return response()->json(['error' => 'No ticket found for that code at your events.'], 404);
        }

        return response()->json(['data' => $this->present($request->user(), $order, $request)]);
    }

    public function updatePayment(Request $request, string $orderId): JsonResponse
    {
        $data = $request->validate([
            'status' => ['required', Rule::in(OrderOperationsService::MANUAL_PAYMENT_STATUSES)],
            'amount_paid' => ['nullable', 'numeric', 'min:0'],
            'notes' => ['nullable', 'string', 'max:500'],
            'rejection_reason' => ['nullable', 'string', 'max:500'],
        ]);

        return $this->run($request, $orderId, fn (User $user, Order $order) => $this->operations->updatePaymentStatus($user, $order, $data));
    }

    public function updateFulfillment(Request $request, string $orderId): JsonResponse
    {
        $data = $request->validate([
            'fulfillment_status' => ['required', Rule::in(OrderOperationsService::FULFILLMENT_STATUSES)],
        ]);

        return $this->run($request, $orderId, fn (User $user, Order $order) => $this->operations->updateFulfillmentStatus($user, $order, $data['fulfillment_status']));
    }

    public function handover(Request $request, string $orderId, string $itemId): JsonResponse
    {
        $data = $request->validate([
            'quantity_delta' => ['required', 'integer', 'between:-100,100', 'not_in:0'],
            'station' => ['nullable', 'string', 'max:64'],
            'action_type' => ['nullable', Rule::in(['collection', 'reversal', 'supervisor_override'])],
            'override_reason' => ['nullable', 'string', 'max:500'],
            'supervisor_name' => ['nullable', 'string', 'max:120'],
        ]);

        $auditEntry = null;
        $response = $this->run($request, $orderId, function (User $user, Order $order) use ($itemId, $data, &$auditEntry) {
            $result = $this->operations->recordHandover($user, $order, $itemId, (int) $data['quantity_delta'], $data);
            $auditEntry = $result['audit'];

            return $result['order'];
        });

        if ($auditEntry instanceof HandoverAuditLog) {
            $payload = $response->getData(true);
            $payload['audit'] = $this->presentAudit($auditEntry);
            $response->setData($payload);
        }

        return $response;
    }

    public function close(Request $request, string $orderId): JsonResponse
    {
        $data = $request->validate([
            'station' => ['nullable', 'string', 'max:64'],
        ]);

        return $this->run($request, $orderId, fn (User $user, Order $order) => $this->operations->closeOrder($user, $order, $data['station'] ?? null));
    }

    public function cashLedger(Request $request): JsonResponse
    {
        $rows = $this->cash->ledgerQuery($request->user())->limit(1000)->get();

        return response()->json(['data' => $rows->map(fn (CashPayment $row) => $this->presentCash($row))->values()]);
    }

    public function recordCash(Request $request): JsonResponse
    {
        $data = $request->validate([
            'order_id' => ['nullable', 'string'],
            'event_id' => ['nullable', 'string', 'required_without:order_id'],
            'customer_name' => ['nullable', 'string', 'max:120'],
            'customer_phone' => ['nullable', 'string', 'max:32'],
            'amount' => ['required', 'numeric', 'min:0.01', 'max:10000000'],
            'currency' => ['nullable', Rule::in(['JMD', 'USD'])],
            'received_at' => ['nullable', 'date', 'before_or_equal:now'],
            'received_from' => ['nullable', 'string', 'max:120'],
            'notes' => ['nullable', 'string', 'max:500'],
        ]);

        return $this->runMoney($request, function (User $user) use ($data, $request) {
            $result = $this->cash->recordReceipt($user, $data);

            return [
                'receipt' => $this->presentCash($result['receipt']),
                'order' => $result['order'] ? $this->present($user, $result['order'], $request) : null,
            ];
        }, 201);
    }

    public function reverseCash(Request $request, string $receiptId): JsonResponse
    {
        $data = $request->validate([
            'amount' => ['required', 'numeric', 'min:0.01'],
            'reason' => ['required', 'string', 'max:500'],
        ]);

        return $this->runMoney($request, function (User $user) use ($data, $receiptId, $request) {
            $result = $this->cash->reverseReceipt($user, $receiptId, (float) $data['amount'], $data['reason']);

            return [
                'reversal' => $this->presentCash($result['reversal']),
                'order' => $result['order'] ? $this->present($user, $result['order'], $request) : null,
            ];
        }, 201);
    }

    public function refund(Request $request, string $orderId): JsonResponse
    {
        $data = $request->validate([
            'amount' => ['required', 'numeric', 'min:0.01'],
            'reason' => ['required', 'string', 'max:500'],
        ]);

        return $this->runMoney($request, fn (User $user) => [
            'data' => $this->present($user, $this->cash->refundOrder($user, $orderId, (float) $data['amount'], $data['reason']), $request),
        ]);
    }

    /**
     * Run a cash/refund operation and translate domain errors to HTTP.
     */
    private function runMoney(Request $request, callable $operation, int $status = 200): JsonResponse
    {
        try {
            return response()->json($operation($request->user()), $status);
        } catch (ModelNotFoundException $e) {
            return response()->json(['error' => $e->getMessage()], 404);
        } catch (SecurityException $e) {
            return response()->json(['error' => $e->getMessage()], 403);
        } catch (\InvalidArgumentException $e) {
            return response()->json(['error' => $e->getMessage()], 422);
        }
    }

    /**
     * Ledger row in the SPA's CashPaymentRecord shape.
     */
    private function presentCash(CashPayment $row): array
    {
        return [
            'id' => $row->id,
            'organizerId' => $row->organizer_id,
            'eventId' => $row->event_id,
            'transactionType' => $row->transaction_type,
            'receiptNumber' => $row->receipt_number,
            'customerName' => $row->customer_name,
            'customerPhone' => $row->customer_phone,
            'eventName' => $row->event_name,
            'orderId' => $row->order_id,
            'orderNumber' => $row->order_number,
            'ticketCode' => $row->ticket_code,
            'amount' => (float) $row->amount,
            'currency' => $row->currency,
            'receivedAt' => $row->received_at?->toIso8601String(),
            'receivedBy' => $row->received_by,
            'notes' => $row->notes,
            'originalRecordId' => $row->original_record_id,
            'reversalOfId' => $row->reversal_of_id,
            'reversalReason' => $row->reversal_reason,
            'reversalAuthorizedBy' => $row->reversal_authorized_by,
            'createdAt' => $row->created_at?->toIso8601String(),
        ];
    }

    /**
     * Resolve the order within the user's reach, run the operation and translate domain errors to HTTP.
     */
    private function run(Request $request, string $orderId, callable $operation): JsonResponse
    {
        $user = $request->user();
        $order = $this->operations->operableOrdersQuery($user)->whereKey($orderId)->first();

        if (!$order) {
            return response()->json(['error' => 'Order not found.'], 404);
        }

        try {
            $updated = $operation($user, $order);
        } catch (SecurityException $e) {
            return response()->json(['error' => $e->getMessage()], 403);
        } catch (\InvalidArgumentException $e) {
            return response()->json(['error' => $e->getMessage()], 422);
        }

        return response()->json(['data' => $this->present($user, $updated, $request)]);
    }

    private function present(User $user, Order $order, Request $request): array
    {
        $capabilities = $this->operations->capabilities($user, $order);
        $data = (new OrderResource($order))->resolve($request);

        if (!$capabilities['verify']) {
            foreach (self::PAYMENT_FIELDS as $field) {
                unset($data[$field]);
            }
        }
        $data['capabilities'] = $capabilities;

        return $data;
    }

    private function presentAudit(HandoverAuditLog $log): array
    {
        return [
            'id' => $log->id,
            'organizerId' => $log->organizer_id,
            'orderId' => $log->order_id,
            'orderNumber' => $log->order_number,
            'ticketCode' => $log->ticket_code,
            'customerName' => $log->customer_name,
            'itemName' => $log->item_name,
            'itemCategory' => $log->item_category,
            'quantityCollected' => $log->quantity_collected,
            'remainingQuantity' => $log->remaining_quantity,
            'timestamp' => (string) $log->timestamp,
            'staffName' => $log->staff_name,
            'station' => $log->station,
            'actionType' => $log->action_type,
            'overrideReason' => $log->override_reason,
            'supervisorName' => $log->supervisor_name,
        ];
    }
}
