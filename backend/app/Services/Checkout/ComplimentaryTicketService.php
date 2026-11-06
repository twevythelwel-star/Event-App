<?php

declare(strict_types=1);

namespace App\Services\Checkout;

use App\Models\Event;
use App\Models\Order;
use App\Models\OrderItem;
use App\Models\User;
use App\Services\AuditLogService;
use App\Services\EventManagementService;
use App\Services\OrderPlacementException;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

/**
 * Free tickets a host issues: guests, press, performers' plus-ones. Only the event's managers can issue them; each is
 * a real, settled order for an existing EventBite account (so the ticket lands in their wallet with a working QR
 * code), at no charge, marked complimentary, with who issued it and why. It takes tickets from the same stock as
 * sales, under the same event lock, so comps never oversell an event.
 */
final class ComplimentaryTicketService
{
    public function __construct(private readonly EventManagementService $events) {}

    public function issue(User $host, string $eventId, string $tierId, int $quantity, string $recipientEmail, string $reason): Order
    {
        $event = $this->events->findEvent($host, $eventId);
        $quantity = max(1, min(20, $quantity));
        $reason = trim($reason);
        if (mb_strlen($reason) < 3) {
            throw new OrderPlacementException('Say why these tickets are complimentary (for the audit log).');
        }
        $recipient = User::whereRaw('LOWER(email) = ?', [mb_strtolower(trim($recipientEmail))])->first();
        if (!$recipient) {
            throw new OrderPlacementException('No EventBite account uses that email. Ask the guest to create one first, then issue the tickets.');
        }

        return DB::transaction(function () use ($host, $event, $tierId, $quantity, $recipient, $reason) {
            $locked = Event::withoutGlobalScopes()->whereKey($event->id)->lockForUpdate()->firstOrFail();
            $tiers = is_array($locked->ticket_tiers) ? $locked->ticket_tiers : [];
            $index = collect($tiers)->search(fn ($t) => (string) ($t['id'] ?? '') === $tierId);
            if ($index === false) {
                throw new OrderPlacementException('That ticket type is not part of this event.');
            }
            $tier = $tiers[$index];
            $available = TicketInventory::availableFor($locked, $tier, null);
            if ($available !== null && $available < $quantity) {
                throw new OrderPlacementException("Only {$available} " . ($tier['name'] ?? 'tickets') . ' are left to give.');
            }
            if (($key = TicketTierCatalog::availabilityKey($tier)) !== null) {
                $tiers[$index][$key] = max(0, (int) $tier[$key] - $quantity);
                $locked->ticket_tiers = $tiers;
                $locked->save();
            }

            do {
                $orderNumber = 'COMP-' . date('Y') . '-' . random_int(100000, 999999);
                $ticketCode = 'SFF-' . strtoupper(Str::random(6));
            } while (Order::withoutGlobalScopes()->where('order_number', $orderNumber)->orWhere('ticket_code', $ticketCode)->exists());
            $currency = (string) ($locked->currency ?: 'USD');

            $order = Order::create([
                'organizer_id' => $locked->organizer_id, 'event_id' => $locked->id, 'user_id' => $recipient->id,
                'ticket_code' => $ticketCode, 'order_number' => $orderNumber, 'customer_name' => $recipient->name,
                'customer_phone' => $recipient->phone ?? '', 'email' => $recipient->email, 'method' => 'complimentary',
                'currency' => $currency, 'subtotal_minor' => 0, 'total_minor' => 0, 'amount_paid_minor' => 0,
                'status' => 'paid', 'fulfillment_status' => 'unprepared', 'secure_ticket_token' => 'tok_' . Str::random(32),
                'all_handed_over' => false, 'verified_at' => now(), 'is_complimentary' => true, 'issued_by' => $host->id,
                'complimentary_reason' => mb_substr($reason, 0, 200),
            ]);
            $line = new OrderItem([
                'order_id' => $order->id, 'ticket_tier_id' => $tierId, 'name' => (string) ($tier['name'] ?? 'Admission ticket') . ' (complimentary)',
                'category' => 'Tickets', 'pricing_type' => 'fixed', 'base_price' => '0.00', 'selected_options_summary' => ['Complimentary'],
                'options_minor' => 0, 'unit_price_minor' => 0, 'currency' => $currency, 'quantity' => $quantity, 'collected_quantity' => 0,
                'handover_status' => 'pending', 'line_type' => OrderItem::TICKET,
            ]);
            $line->id = (string) Str::uuid();
            $line->save();

            AuditLogService::log(action: 'complimentary_tickets_issued', targetEntity: 'orders', targetId: (string) $order->id,
                details: "{$locked->title}: {$quantity} complimentary " . ($tier['name'] ?? 'tickets') . " for {$recipient->email}. Reason: {$reason}",
                actor: $host, organizerId: (string) $locked->organizer_id, eventId: (string) $locked->id);

            return $order;
        });
    }
}
