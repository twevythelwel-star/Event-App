<?php

namespace App\Http\Controllers;

use App\Http\Requests\StoreOrderRequest;
use App\Models\Event;
use App\Services\OrderPlacementService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class OrderController extends Controller
{
    public function __construct(
        protected OrderPlacementService $orderService
    ) {}

    /**
     * Authenticated Checkout: Place an advance food order.
     */
    public function store(StoreOrderRequest $request, string $eventSlug): JsonResponse
    {
        $event = Event::where('slug', $eventSlug)->first();
        if (!$event) {
            return response()->json(['error' => 'Event not found.'], 404);
        }

        $result = $this->orderService->placeOrder($event, $request->validated(), $request->user());

        if (!$result['success']) {
            $status = $result['status_code'] ?? 422;
            return response()->json(['error' => $result['error']], $status);
        }

        $order = $result['order'];

        return response()->json([
            'message' => 'Order submitted successfully.',
            'order_number' => $order->order_number,
            'ticket_code' => $order->ticket_code,
            'total_amount' => $order->total_amount,
            'currency' => $order->currency,
            'status' => $order->status,
            'fulfillment_status' => $order->fulfillment_status,
            'secure_ticket_token' => $order->secure_ticket_token,
            'order' => $order,
        ], 201);
    }

    /**
     * Retrieve Order Details securely via token or code + phone, with IDOR protection.
     */
    public function retrieve(Request $request): JsonResponse
    {
        $identifier = $request->input('identifier') ?? $request->input('ticket_code') ?? $request->input('order_number');
        $phoneOrToken = $request->input('phone') ?? $request->input('token');

        if (!$identifier) {
            return response()->json(['error' => 'An order number, ticket code, or token is required.'], 422);
        }

        $result = $this->orderService->retrieveOrderSecurely($identifier, $phoneOrToken, $request->user());

        if (!$result['success']) {
            $status = $result['status_code'] ?? 403;
            return response()->json(['error' => $result['error']], $status);
        }

        return response()->json([
            'order' => $result['order'],
        ]);
    }
}
