<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\StoreOrderRequest;
use App\Http\Resources\EventResource;
use App\Http\Resources\OrderResource;
use App\Models\Event;
use App\Models\Order;
use App\Services\OrderPlacementService;
use App\Services\TenantContext;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;

/**
 * Public event catalog plus the signed-in attendee's ordering flow for the single-page app.
 */
class CatalogController extends Controller
{
    public function __construct(
        protected OrderPlacementService $orderService
    ) {}

    public function events(): AnonymousResourceCollection
    {
        $events = TenantContext::withoutTenantScope(fn () => Event::query()
            ->where('status', 'published')
            ->with(['menuItems' => fn ($query) => $query->where('is_active', true)->orderBy('sort_order')])
            ->orderBy('advance_order_deadline')
            ->get());

        return EventResource::collection($events);
    }

    public function placeOrder(StoreOrderRequest $request, string $eventSlug): JsonResponse
    {
        $event = TenantContext::withoutTenantScope(
            fn () => Event::where('slug', $eventSlug)->where('status', 'published')->first()
        );

        if (!$event) {
            return response()->json(['error' => 'Event not found.'], 404);
        }

        $result = TenantContext::withoutTenantScope(
            fn () => $this->orderService->placeOrder($event, $request->validated(), $request->user())
        );

        if (!$result['success']) {
            return response()->json(['error' => $result['error']], $result['status_code'] ?? 422);
        }

        return (new OrderResource($result['order']))->response()->setStatusCode(201);
    }

    public function myOrders(Request $request): AnonymousResourceCollection
    {
        $orders = TenantContext::withoutTenantScope(fn () => Order::query()
            ->where('user_id', $request->user()->id)
            ->with('items')
            ->latest()
            ->get());

        return OrderResource::collection($orders);
    }
}
