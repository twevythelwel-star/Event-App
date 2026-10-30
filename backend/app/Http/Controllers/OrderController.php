<?php

namespace App\Http\Controllers;

use App\Http\Requests\StoreOrderRequest;
use App\Models\Event;
use App\Services\OrderPlacementService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

class OrderController extends Controller
{
    public function __construct(
        protected OrderPlacementService $orderService
    ) {}

    /**
     * Authenticated Checkout: place a ticket and/or food order.
     * API callers get JSON; the Blade order form gets a redirect with flash feedback.
     */
    public function store(StoreOrderRequest $request, string $eventSlug): JsonResponse|RedirectResponse
    {
        $wantsJson = $request->expectsJson();
        $event = Event::where('slug', $eventSlug)->first();
        if (!$event) {
            return $wantsJson
                ? response()->json(['error' => 'Event not found.'], 404)
                : redirect()->route('events.index')->with('error', 'That event is no longer available.');
        }

        $result = $this->orderService->placeOrder($event, $request->validated(), $request->user());

        if (!$result['success']) {
            $status = $result['status_code'] ?? 422;
            if ($wantsJson) {
                return response()->json(['error' => $result['error']], $status);
            }

            // Keep what the customer entered and show why it failed (sold out, ordering closed, ...).
            return back()->withInput()->with('error', $result['error']);
        }

        $order = $result['order'];

        if (!$wantsJson) {
            return redirect()->route('attendee.dashboard')->with('success',
                "Order {$order->order_number} placed. Your ticket code is {$order->ticket_code}. "
                . ($order->method === 'cash'
                    ? 'Pay cash at pickup; staff will mark it paid.'
                    : 'It shows Pending until the organiser confirms your payment.'));
        }

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
