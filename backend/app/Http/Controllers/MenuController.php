<?php

namespace App\Http\Controllers;

use App\Http\Requests\StoreMenuItemRequest;
use App\Models\Event;
use App\Models\MenuItem;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class MenuController extends Controller
{
    /**
     * List active menu items for an event (Public & Organizer).
     */
    public function index(string $eventSlug): JsonResponse
    {
        $event = Event::where('slug', $eventSlug)->first();
        if (!$event) {
            return response()->json(['error' => 'Event not found.'], 404);
        }

        $items = MenuItem::where('event_id', $event->id)
            ->where('is_active', true)
            ->orderBy('sort_order')
            ->get();

        return response()->json([
            'event' => [
                'title' => $event->title,
                'currency' => $event->currency,
                'packaging_fee_usd' => $event->packaging_fee_usd,
                'packaging_fee_jmd' => $event->packaging_fee_jmd,
                'is_ordering_open' => $event->is_ordering_open,
            ],
            'menu' => $items,
        ]);
    }

    /**
     * Create a new menu item (Authorized Event Owners only).
     */
    public function store(StoreMenuItemRequest $request, string $eventSlug): JsonResponse
    {
        $event = Event::where('slug', $eventSlug)->first();
        if (!$event) {
            return response()->json(['error' => 'Event not found.'], 404);
        }

        $data = $request->validated();
        $data['event_id'] = $event->id;
        $data['organizer_id'] = $event->organizer_id;

        $item = MenuItem::create($data);

        return response()->json([
            'message' => 'Menu item created successfully.',
            'item' => $item,
        ], 201);
    }

    /**
     * Update an existing menu item.
     */
    public function update(StoreMenuItemRequest $request, string $eventSlug, string $itemId): JsonResponse
    {
        $event = Event::where('slug', $eventSlug)->firstOrFail();

        // Scope lookup to the authorized event so items from other organizers cannot be targeted
        $item = MenuItem::where('event_id', $event->id)->findOrFail($itemId);
        $item->update($request->validated());

        return response()->json([
            'message' => 'Menu item updated successfully.',
            'item' => $item,
        ]);
    }

    /**
     * Soft delete a menu item.
     * Note: Historical orders are untouched because order items are frozen snapshots.
     */
    public function destroy(Request $request, string $eventSlug, string $itemId): JsonResponse
    {
        $event = Event::where('slug', $eventSlug)->firstOrFail();

        if (!StoreMenuItemRequest::canManageMenu($request->user(), $event)) {
            return response()->json(['error' => 'You are not authorized to manage this menu.'], 403);
        }

        $item = MenuItem::where('event_id', $event->id)->findOrFail($itemId);
        $item->delete();

        return response()->json([
            'message' => 'Menu item removed from active menu. Historical order records are preserved.',
        ]);
    }
}
