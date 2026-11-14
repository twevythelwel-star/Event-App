<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Event;
use App\Models\Order;
use App\Models\OrderCharge;
use App\Models\OrderItem;
use App\Services\TenantContext;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class EventReportsController extends Controller
{
    private function eventFor(Request $request, string $eventId): Event
    {
        // An event the signed-in user manages (its organizer's managers and system administrators); 404 otherwise.
        // These routes have no tenant middleware, so the active tenant cannot be used to find the event.
        return app(\App\Services\EventManagementService::class)->findEvent($request->user(), $eventId);
    }

    public function financialReport(Request $request, string $eventId): JsonResponse
    {
        $event = $this->eventFor($request, $eventId);
        $base = Order::withoutGlobalScopes()->where('event_id', $event->id)->whereNull('deleted_at')->where('currency', 'USD');
        $sum = fn ($query, string $column = 'total_minor') => (int) (clone $query)->sum($column);
        $count = fn ($query) => (int) (clone $query)->count();
        $paid = (clone $base)->whereIn('status', ['paid', 'confirmed']);
        $pending = (clone $base)->whereIn('status', ['payment_submitted', 'under_verification', 'pending_verification', 'awaiting_payment']);
        $ticketIds = (clone $base)->select('id')->whereExists(fn ($q) => $q->selectRaw('1')->from('order_items')->whereColumn('order_items.order_id', 'orders.id')->whereNotNull('ticket_tier_id'));
        $foodIds = (clone $base)->select('id')->whereExists(fn ($q) => $q->selectRaw('1')->from('order_items')->whereColumn('order_items.order_id', 'orders.id')->whereNotNull('menu_item_id'));
        $orderIds = (clone $base)->select('id');
        $fees = (int) OrderCharge::whereIn('order_id', $orderIds)->where('currency', 'USD')->whereIn('kind', ['platform_fee', 'host_fee'])->sum('amount_minor');
        $taxes = (int) OrderCharge::whereIn('order_id', $orderIds)->where('currency', 'USD')->where('kind', 'tax')->sum('amount_minor');
        $refunds = (clone $base)->where('status', 'refunded');
        $minorToMajor = fn (int $minor) => \App\Support\Money::ofMinor($minor, 'USD')->toMajorNumber();
        $ticketQuantity = (int) OrderItem::whereIn('order_id', $ticketIds)->whereNotNull('ticket_tier_id')->sum('quantity');
        // Portions to prepare: single items and package components, not the package lines that group them.
        $foodQuantity = (int) OrderItem::whereIn('order_id', $foodIds)->whereNotNull('menu_item_id')->where('line_type', '!=', OrderItem::PACKAGE)->sum('quantity');

        return response()->json([
            'event_id' => $event->id,
            'event_title' => $event->title,
            'currency' => 'USD',
            'financials' => [
                'gross_sales' => $minorToMajor($sum($base)),
                'verified_revenue' => $minorToMajor($sum($paid)),
                'refunds' => $minorToMajor($sum($base, 'refunded_minor')),
                'fees' => $minorToMajor($fees),
                'taxes' => $minorToMajor($taxes),
                'net_amount' => $minorToMajor($sum($base) - $fees - $taxes - $sum($base, 'refunded_minor')),
                'pending_payments' => $minorToMajor($sum($pending)),
            ],
            'orders' => [
                'total_orders' => $count($base), 'paid_orders' => $count($paid),
                'pending_orders' => $count($pending), 'refunded_orders' => $count($refunds),
            ],
            'sales_breakdown' => [
                'ticket_sales' => $minorToMajor($sum($ticketIds)), 'ticket_count' => $ticketQuantity,
                'food_sales' => $minorToMajor($sum($foodIds)), 'food_item_count' => $foodQuantity,
            ],
        ]);
    }

    public function checkInReport(Request $request, string $eventId): JsonResponse
    {
        $event = $this->eventFor($request, $eventId);
        $tickets = OrderItem::query()->join('orders', 'orders.id', '=', 'order_items.order_id')
            ->where('orders.event_id', $event->id)->whereNull('orders.deleted_at')->whereNotNull('order_items.ticket_tier_id');
        $total = (int) (clone $tickets)->sum('order_items.quantity');
        $checked = (int) (clone $tickets)->whereNotNull('orders.checked_in_at')->sum('order_items.quantity');
        $recent = Order::withoutGlobalScopes()->where('event_id', $event->id)->whereNull('deleted_at')
            ->whereNotNull('checked_in_at')->orderByDesc('checked_in_at')->limit(20)
            ->get(['order_number', 'ticket_code', 'customer_name', 'checked_in_at', 'checked_in_by']);

        return response()->json([
            'event_id' => $event->id, 'event_title' => $event->title,
            'check_ins' => ['total_tickets' => $total, 'checked_in' => $checked, 'pending_check_in' => max(0, $total - $checked), 'check_in_rate' => $total > 0 ? round($checked / $total * 100, 2) : 0],
            'recent_check_ins' => $recent->map(fn ($o) => ['order_number' => $o->order_number, 'ticket_code' => $o->ticket_code, 'customer_name' => $o->customer_name, 'checked_in_at' => $o->checked_in_at?->format('Y-m-d H:i:s'), 'checked_in_by' => $o->checked_in_by]),
        ]);
    }

    public function fulfillmentReport(Request $request, string $eventId): JsonResponse
    {
        $event = $this->eventFor($request, $eventId);
        $food = OrderItem::query()->join('orders', 'orders.id', '=', 'order_items.order_id')
            ->where('orders.event_id', $event->id)->whereNull('orders.deleted_at')->where('orders.status', 'paid')->whereNotNull('order_items.menu_item_id')
            ->where('order_items.line_type', '!=', OrderItem::PACKAGE);
        $total = (int) (clone $food)->sum('order_items.quantity');
        $collected = (int) (clone $food)->sum('order_items.collected_quantity');
        $byVendor = (clone $food)->select('order_items.vendor_assignment')
            ->selectRaw('SUM(order_items.quantity) as total, SUM(order_items.collected_quantity) as collected')
            ->groupBy('order_items.vendor_assignment')->get()->mapWithKeys(fn ($row) => [$row->vendor_assignment => ['total' => (int) $row->total, 'collected' => (int) $row->collected, 'remaining' => max(0, (int) $row->total - (int) $row->collected)]]);
        $attention = (clone $food)->whereColumn('order_items.collected_quantity', '<', 'order_items.quantity')
            ->join('orders as attention_orders', 'attention_orders.id', '=', 'order_items.order_id')
            ->addSelect(['order_items.name', 'order_items.quantity', 'order_items.collected_quantity', 'order_items.vendor_assignment', 'attention_orders.order_number'])
            ->orderBy('attention_orders.created_at')->paginate(min(100, max(1, (int) $request->query('per_page', 50))))
            ->through(fn ($item) => ['name' => $item->name, 'order_number' => $item->order_number, 'total' => (int) $item->quantity, 'collected' => (int) $item->collected_quantity, 'remaining' => (int) $item->quantity - (int) $item->collected_quantity, 'vendor' => $item->vendor_assignment]);

        $remainingUnsoldStock = (int) \App\Models\MenuItem::withoutGlobalScopes()
            ->where('event_id', $event->id)
            ->where('is_active', true)
            ->whereNotNull('available_stock')
            ->sum('available_stock');
        $uncollectedPortions = max(0, $total - $collected);
        $recommendedCookTarget = (int) ceil($total * 1.1);

        return response()->json([
            'event_id' => $event->id, 'event_title' => $event->title,
            'fulfillment' => ['total_portions' => $total, 'collected_portions' => $collected, 'remaining_portions' => $uncollectedPortions, 'fulfillment_rate' => $total > 0 ? round($collected / $total * 100, 2) : 0],
            'inventory_and_waste' => [
                'remaining_unsold_stock' => $remainingUnsoldStock,
                'recommended_cook_target' => $recommendedCookTarget,
                'uncollected_portions' => $uncollectedPortions,
                'estimated_waste_rate' => $recommendedCookTarget > 0 ? round($uncollectedPortions / $recommendedCookTarget * 100, 2) : 0,
            ],
            'by_vendor' => $byVendor, 'items_needing_attention' => $attention->items(), 'pagination' => ['current_page' => $attention->currentPage(), 'last_page' => $attention->lastPage(), 'per_page' => $attention->perPage(), 'total' => $attention->total()],
        ]);
    }
}
