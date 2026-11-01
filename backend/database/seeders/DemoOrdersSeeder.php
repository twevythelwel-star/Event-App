<?php

namespace Database\Seeders;

use App\Models\Event;
use App\Models\EventWorkerAssignment;
use App\Models\Order;
use App\Models\User;
use App\Models\UserRole;
use App\Services\OrderPlacementService;
use App\Services\TenantContext;
use Illuminate\Database\Seeder;

/**
 * Local development orders for the South Florida Jamaican Fish Fry sample event (owned by the demo host), in each payment state
 * the host verification queue handles, plus a food-station staff account assigned to that event.
 * Never run in production.
 */
class DemoOrdersSeeder extends Seeder
{
    public function run(OrderPlacementService $orders): void
    {
        if (app()->environment('production')) {
            return;
        }

        $attendee = User::where('email', 'attendee@islandevents.example')->firstOrFail();
        $event = TenantContext::withoutTenantScope(fn () => Event::where('slug', 'south-florida-jamaican-fish-fry')->firstOrFail());

        $demoOrders = [
            // Transfer submitted with a bank reference: waiting in the host's verification queue
            ['method' => 'bank', 'payment_reference' => 'DEMO-ACH-882104', 'name' => 'Shanice Brown', 'items' => [['menu_item_id' => 'kgn-escovitch', 'quantity' => 2, 'selected_choices' => ['fish-size' => 1, 'sides' => 0]]]],
            // Demo transfer with a sender name only; no real payment was made
            ['method' => 'bank', 'sender_name' => 'Marcus Reid', 'name' => 'Marcus Reid', 'items' => [['menu_item_id' => 'kgn-fried-chicken', 'quantity' => 3]]],
            // Cash at pickup: authorized unpaid, so the kitchen can prepare it
            ['method' => 'cash', 'name' => 'Paulette Grant', 'items' => [['menu_item_id' => 'kgn-fried-chicken', 'quantity' => 1], ['menu_item_id' => 'kgn-escovitch', 'quantity' => 1]]],
            // Verified and paid: ready for the scanner
            ['method' => 'bank', 'payment_reference' => 'DEMO-ACH-55127', 'name' => 'Andre Campbell', 'paid' => true, 'items' => [['menu_item_id' => 'kgn-escovitch', 'quantity' => 1], ['menu_item_id' => 'kgn-fried-chicken', 'quantity' => 2]]],
        ];

        foreach ($demoOrders as $demo) {
            // Re-running the seeder must not pile up more demo orders: each one is placed only once.
            $exists = Order::withoutGlobalScopes()->where('event_id', $event->id)
                ->where('user_id', $attendee->id)->where('customer_name', $demo['name'])->exists();
            if ($exists) {
                continue;
            }

            $result =TenantContext::withoutTenantScope(fn () => $orders->placeOrder($event, [
                'customer_name' => $demo['name'],
                'customer_phone' => '+1 305 555 01' . random_int(10, 99),
                'method' => $demo['method'],
                'currency' => 'USD',
                'payment_reference' => $demo['payment_reference'] ?? null,
                'sender_name' => $demo['sender_name'] ?? null,
                'items' => $demo['items'],
            ], $attendee));

            if (!$result['success']) {
                throw new \RuntimeException("Demo order failed: {$result['error']}");
            }

            if (!empty($demo['paid'])) {
                /** @var Order $order */
                $order = $result['order'];
                $order->update([
                    'status' => 'paid',
                    'amount_paid' => $order->total_amount,
                    'verified_at' => now(),
                    'verified_by' => 'Demo Event Host',
                    'fulfillment_status' => 'ready_for_pickup',
                ]);
            }
        }

        // Food-station staff member assigned to the demo event, not to the organizer membership
        $staff = User::updateOrCreate(['email' => 'staff@islandevents.example'], [
            'name' => 'Demo Food Station Staff',
            'phone' => '+1 (305) 555-0177',
            'password' => password_hash(DemoAccountsSeeder::DEMO_PASSWORD, PASSWORD_DEFAULT),
            'status' => 'active',
            'email_verified_at' => now(),
        ]);
        UserRole::updateOrCreate(['user_id' => $staff->id, 'role' => 'attendee'], ['is_active' => true, 'assigned_at' => now()]);

        EventWorkerAssignment::updateOrCreate(
            ['user_id' => $staff->id, 'event_id' => $event->id],
            [
                'organizer_id' => $event->organizer_id,
                'worker_role' => 'food_station_worker',
                'station_assignment' => 'all',
                'status' => 'active',
                'is_active' => true,
                'starts_at' => now()->subDay(),
                'expires_at' => now()->addYear(),
            ]
        );
    }
}
