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
use Illuminate\Validation\ValidationException;

/**
 * Public event catalog plus the signed-in attendee's ordering flow for the single-page app.
 */
class CatalogController extends Controller
{
    public function __construct(
        protected OrderPlacementService $orderService
    ) {}

    public function events(Request $request): AnonymousResourceCollection
    {
        $search = trim((string) $request->query('search', ''));
        $category = trim((string) $request->query('category', ''));
        $stateCode = strtoupper(trim((string) $request->query('state', '')));
        $city = trim((string) $request->query('city', ''));
        $zipCode = trim((string) $request->query('zip', ''));
        $latitude = $request->query('latitude');
        $longitude = $request->query('longitude');
        $radiusMiles = (float) $request->query('radius_miles', 25);
        $priceMin = $request->query('price_min');
        $priceMax = $request->query('price_max');
        $availableOnly = $request->boolean('available');

        $request->validate([
            'state' => ['nullable', 'string', 'size:2'],
            'city' => ['nullable', 'string', 'max:120'],
            'zip' => ['nullable', 'regex:/^\d{5}(?:-\d{4})?$/'],
            'latitude' => ['nullable', 'numeric', 'between:-90,90'],
            'longitude' => ['nullable', 'numeric', 'between:-180,180'],
            'radius_miles' => ['nullable', 'numeric', 'min:1', 'max:250'],
            'price_min' => ['nullable', 'numeric', 'min:0'],
            'price_max' => ['nullable', 'numeric', 'min:0', 'gte:price_min'],
            'available' => ['nullable', 'boolean'],
        ]);
        if (($latitude !== null) !== ($longitude !== null)) {
            throw ValidationException::withMessages(['latitude' => 'Latitude and longitude must be supplied together.']);
        }

        $events = TenantContext::withoutTenantScope(function () use (
            $search, $category, $stateCode, $city, $zipCode,
        ) {
            $query = Event::query()
                ->where('status', 'published')
                ->where('country_code', 'US')
                ->with(['state', 'menuItems' => fn ($q) => $q->where('is_active', true)->orderBy('sort_order')]);

            if ($stateCode !== '') {
                $query->where('state_code', $stateCode);
            }
            if ($city !== '') {
                $query->whereRaw('LOWER(city) = ?', [mb_strtolower($city)]);
            }
            if ($zipCode !== '') {
                $query->where('zip_code', $zipCode);
            }

            if ($search !== '') {
                $like = '%' . strtolower($search) . '%';
                $query->where(function ($q) use ($like) {
                    $q->whereRaw('LOWER(title) LIKE ?', [$like])
                        ->orWhereRaw('LOWER(COALESCE(category, \'\')) LIKE ?', [$like])
                        ->orWhereRaw('LOWER(COALESCE(venue_name, \'\')) LIKE ?', [$like])
                        ->orWhereRaw('LOWER(COALESCE(venue_address, \'\')) LIKE ?', [$like])
                        ->orWhereRaw('LOWER(COALESCE(organizer_name, \'\')) LIKE ?', [$like])
                        ->orWhereRaw('LOWER(COALESCE(description, \'\')) LIKE ?', [$like]);
                });
            }

            if ($category !== '' && strtolower($category) !== 'all' && strtolower($category) !== 'all events') {
                $catLike = '%' . strtolower($category) . '%';
                $query->where(function ($q) use ($catLike) {
                    $q->whereRaw('LOWER(COALESCE(category, \'\')) LIKE ?', [$catLike])
                        ->orWhereRaw('LOWER(title) LIKE ?', [$catLike]);
                });
            }

            return $query->orderBy('advance_order_deadline')->get();
        });

        if ($latitude !== null && $longitude !== null) {
            $events = $events->filter(function (Event $event) use ($latitude, $longitude, $radiusMiles) {
                if ($event->latitude === null || $event->longitude === null) {
                    return false;
                }
                $lat1 = deg2rad((float) $latitude);
                $lat2 = deg2rad((float) $event->latitude);
                $deltaLat = $lat2 - $lat1;
                $deltaLon = deg2rad((float) $event->longitude - (float) $longitude);
                $a = sin($deltaLat / 2) ** 2 + cos($lat1) * cos($lat2) * sin($deltaLon / 2) ** 2;
                $distanceMiles = 3959 * 2 * asin(min(1, sqrt($a)));

                return $distanceMiles <= $radiusMiles;
            })->values();
        }

        if ($availableOnly) {
            $events = $events->filter(fn (Event $event) => $event->isOrderingAllowed());
        }
        if ($priceMin !== null || $priceMax !== null) {
            $events = $events->filter(function (Event $event) use ($priceMin, $priceMax) {
                $prices = collect($event->ticket_tiers ?? [])->pluck('priceUSD')
                    ->merge($event->menuItems->where('is_active', true)->pluck('price_usd'))
                    ->map(fn ($price) => (float) $price)->filter(fn ($price) => $price >= 0);
                $lowest = $prices->isEmpty() ? 0 : $prices->min();
                return ($priceMin === null || $lowest >= (float) $priceMin)
                    && ($priceMax === null || $lowest <= (float) $priceMax);
            })->values();
        }

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
