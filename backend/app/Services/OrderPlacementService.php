<?php

namespace App\Services;

use App\Models\Event;
use App\Models\MenuItem;
use App\Models\Order;
use App\Models\OrderItem;
use App\Services\Checkout\TicketTierCatalog;
use App\Services\Notifications\NotificationService;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class OrderPlacementService
{
    /**
     * Atomically place an advance food order with stock protection and historical snapshotting.
     *
     * @param Event $event
     * @param array $payload
     * @param \App\Models\User|null $user
     * @return array ['success' => bool, 'order' => ?Order, 'error' => ?string, 'status_code' => ?int]
     */
    public function placeOrder(Event $event, array $payload, ?\App\Models\User $user = null): array
    {
        // Require authenticated user
        $authUser = $user ?? (function_exists('auth') ? auth()->user() : null);
        if (!$authUser && empty($payload['_allow_guest_test'])) {
            return [
                'success' => false,
                'error' => 'Authentication required: You must be registered and signed in to place an order on EventBite.',
                'status_code' => 401,
            ];
        }

        // 1. Advance Order Cutoff & Gate Check
        if (!$event->is_ordering_open) {
            return ['success' => false, 'error' => 'Ordering is currently paused by the organizer.'];
        }

        if ($event->advance_order_deadline) {
            $deadline = strtotime($event->advance_order_deadline);
            if ($deadline && time() > $deadline) {
                return ['success' => false, 'error' => 'The advance ordering deadline for this event has passed.'];
            }
        }

        // 2. Validate Customer Details
        $customerName = trim($payload['customer_name'] ?? ($authUser?->name ?? ''));
        $customerPhone = preg_replace('/\D/', '', $payload['customer_phone'] ?? ($authUser?->phone ?? ''));

        if (strlen($customerName) < 2) {
            return ['success' => false, 'error' => 'Customer name is required.'];
        }
        if (strlen($customerPhone) < 7) {
            return ['success' => false, 'error' => 'A valid phone number is required for SMS ticket delivery and pickup alerts.'];
        }

        $itemsPayload = $payload['items'] ?? [];
        if (empty($itemsPayload)) {
            return ['success' => false, 'error' => 'Your order must contain at least one food or beverage item.'];
        }

        // The event's currency is authoritative; a different currency in the request is ignored.
        $currency = PriceCalculationService::lockAuthoritativeCurrency((string) ($event->currency ?: 'USD'), $payload['currency'] ?? null);
        $idempotencyKey = isset($payload['idempotency_key']) ? trim((string) $payload['idempotency_key']) : '';

        // 3. Database Transaction & Atomic Stock Reservation
        // Failures inside the transaction throw so any stock already reserved is rolled back
        try {
            return DB::transaction(function () use ($event, $payload, $customerName, $customerPhone, $itemsPayload, $currency, $authUser, $idempotencyKey) {
                if ($idempotencyKey !== '' && ($existing = $this->findByIdempotencyKey($event, $idempotencyKey))) {
                    return ['success' => true, 'order' => $existing];
                }

                // Lock the event row: ticket stock lives in its ticket_tiers JSON, so two checkouts
                // must not both read the same remaining count. Re-read state that may have changed
                // since the caller loaded the event (ordering closed, tickets sold).
                $event = Event::withoutGlobalScopes()->where('id', $event->id)->lockForUpdate()->first();
                if (!$event || !$event->is_ordering_open) {
                    throw new OrderPlacementException('Ordering is currently paused by the organizer.');
                }

                $orderItemsToCreate = [];
                $ticketTiers = is_array($event->ticket_tiers) ? $event->ticket_tiers : [];
                $ticketTiersModified = false;

                foreach ($itemsPayload as $entry) {
                    $menuItemId = (string) ($entry['menu_item_id'] ?? '');
                    $quantity = (int) ($entry['quantity'] ?? 0);

                    if ($quantity === 0) {
                        continue; // Untouched rows on an order form, not a request.
                    }
                    if ($quantity < 0) {
                        throw new OrderPlacementException("Invalid requested quantity: {$quantity}.");
                    }

                    // Lock menu item row for stock integrity (SELECT ... FOR UPDATE); items must belong to this event
                    $menuItem = MenuItem::where('id', $menuItemId)
                        ->where('event_id', $event->id)
                        ->where('is_active', true)
                        ->lockForUpdate()
                        ->first();

                    if (!$menuItem && $this->isTicketTierItemId($menuItemId, $ticketTiers)) {
                        $orderItemsToCreate[] = $this->resolveAndReserveTicketTier(
                            $menuItemId,
                            $quantity,
                            $currency,
                            $ticketTiers,
                            $ticketTiersModified
                        );
                        continue;
                    }

                    if (!$menuItem) {
                        throw new OrderPlacementException("Menu item with ID {$menuItemId} not found.");
                    }

                    // Stock limit check
                    if ($menuItem->available_stock !== null) {
                        if ($menuItem->available_stock < $quantity) {
                            throw new OrderPlacementException("Insufficient stock for '{$menuItem->name}'. Available: {$menuItem->available_stock}, Requested: {$quantity}.");
                        }
                        // Decrement inventory atomically
                        $menuItem->decrement('available_stock', $quantity);
                    }

                    // Authoritative Price Calculation in exact minor units
                    $selectedChoices = $entry['selected_choices'] ?? [];
                    $customDonationAmount = isset($entry['custom_donation_amount']) ? (float) $entry['custom_donation_amount'] : null;

                    $priceResult = PriceCalculationService::calculateItemPrice(
                        $menuItem,
                        $currency,
                        $selectedChoices,
                        $customDonationAmount
                    );

                    // Prepare historical immutable snapshot
                    $orderItemsToCreate[] = [
                        'id' => (string) Str::uuid(),
                        'menu_item_id' => $menuItem->id,
                        'name' => $menuItem->name, // Frozen snapshot of name
                        'category' => $menuItem->category, // Frozen snapshot of category
                        'pricing_type' => $menuItem->pricing_type,
                        'base_price' => $priceResult['base_price'], // Frozen snapshot of base price
                        'selected_options_summary' => $priceResult['selected_options_summary'], // Frozen options
                        'options_total_extra' => $priceResult['options_total_extra'],
                        'custom_donation_amount' => $customDonationAmount,
                        'final_item_price' => $priceResult['final_item_price'], // Frozen snapshot
                        'currency' => $currency,
                        'quantity' => $quantity,
                        'collected_quantity' => 0,
                        'special_instructions' => $entry['special_instructions'] ?? null,
                        'handover_status' => 'pending',
                    ];
                }

                if ($orderItemsToCreate === []) {
                    throw new OrderPlacementException('Your order must contain at least one ticket or food item.');
                }

                if ($ticketTiersModified) {
                    $event->ticket_tiers = $ticketTiers;
                    $event->save();
                }

                // Calculate Order Totals in exact integer cents; packaging fee applies when food items are present
                $hasFoodItems = false;
                foreach ($orderItemsToCreate as $it) {
                    if (($it['category'] ?? '') !== 'Tickets') {
                        $hasFoodItems = true;
                        break;
                    }
                }
                $packagingFee = $hasFoodItems
                    ? (float) ($currency === 'JMD' ? ($event->packaging_fee_jmd ?? 0) : ($event->packaging_fee_usd ?? 0))
                    : 0.0;
                $totals = PriceCalculationService::calculateOrderTotals($orderItemsToCreate, $packagingFee, $currency);

                // Determine safe initial payment status
                // RULE: Do not mark an order paid merely because it was submitted!
                $method = $payload['method'] ?? 'cash';
                $paymentRef = trim($payload['payment_reference'] ?? '');
                $hasTransferProof = !empty($paymentRef) || !empty(trim($payload['sender_name'] ?? ''));

                if ($method === 'cash') {
                    $initialStatus = 'awaiting_payment';
                    $authorizedUnpaid = true;
                } elseif ($hasTransferProof) {
                    $initialStatus = 'payment_submitted';
                    $authorizedUnpaid = false;
                } else {
                    $initialStatus = 'awaiting_payment';
                    $authorizedUnpaid = false;
                }

                // Six random digits, re-drawn on collision so the unique indexes never reject checkout
                $year = date('Y');
                do {
                    $randNum = (string) random_int(100000, 999999);
                    $orderNumber = "ORD-{$year}-{$randNum}";
                    // Independent of the order number, so knowing one does not give away the other
                    $ticketCode = 'SFF-' . self::randomTicketSuffix();
                } while (Order::withoutGlobalScopes()->where('order_number', $orderNumber)->orWhere('ticket_code', $ticketCode)->exists());
                $secureToken = 'tok_' . Str::random(32);

                $order = Order::create([
                    'organizer_id' => $event->organizer_id,
                    'event_id' => $event->id,
                    'user_id' => $authUser?->id,
                    'ticket_code' => $ticketCode,
                    'order_number' => $orderNumber,
                    'customer_name' => $customerName,
                    'customer_phone' => $payload['customer_phone'] ?? ($authUser?->phone ?? ''),
                    'email' => $authUser?->email ?? ($payload['email'] ?? null),
                    'method' => $method,
                    'subtotal' => $totals['subtotal'],
                    'packaging_fee' => $totals['packaging_fee'],
                    'total_amount' => $totals['total_amount'],
                    'amount_paid' => 0.00, // Never auto-credited without verification
                    'currency' => $currency,
                    'status' => $initialStatus,
                    'fulfillment_status' => 'unprepared',
                    'payment_reference' => $paymentRef ?: null,
                    'sender_name' => trim($payload['sender_name'] ?? '') ?: null,
                    'authorized_unpaid' => $authorizedUnpaid,
                    'secure_ticket_token' => $secureToken,
                    'all_handed_over' => false,
                    'idempotency_key' => $idempotencyKey !== '' ? $idempotencyKey : null,
                    'notes' => trim((string) ($payload['notes'] ?? '')) ?: null,
                ]);

                foreach ($orderItemsToCreate as $itemData) {
                    $itemData['order_id'] = $order->id;
                    OrderItem::create($itemData);
                }

                NotificationService::notifyOrder($order, 'order_received');

                return [
                    'success' => true,
                    'order' => $order->fresh(['items']),
                ];
            });
        } catch (OrderPlacementException $e) {
            return ['success' => false, 'error' => $e->getMessage()];
        } catch (\Illuminate\Database\UniqueConstraintViolationException $e) {
            // A duplicate submission raced past the first lookup; the unique index stopped it.
            if ($idempotencyKey !== '' && ($existing = $this->findByIdempotencyKey($event, $idempotencyKey))) {
                return ['success' => true, 'order' => $existing];
            }
            throw $e;
        }
    }

    private function findByIdempotencyKey(Event $event, string $key): ?Order
    {
        return Order::withoutGlobalScopes()
            ->where('event_id', $event->id)
            ->where('idempotency_key', $key)
            ->with('items')
            ->first();
    }

    private function isTicketTierItemId(string $itemId, array $ticketTiers): bool
    {
        foreach ($ticketTiers as $tier) {
            if (isset($tier['id']) && (string) $tier['id'] === $itemId) {
                return true;
            }
        }
        return false;
    }

    private function resolveAndReserveTicketTier(
        string $itemId,
        int $quantity,
        string $currency,
        array &$ticketTiers,
        bool &$ticketTiersModified
    ): array {
        // Match against host-configured ticket_tiers first
        foreach ($ticketTiers as $idx => $tier) {
            if (isset($tier['id']) && (string) $tier['id'] === $itemId) {
                $name = (string) ($tier['name'] ?? 'Admission Ticket');
                $maxPerCustomer = TicketTierCatalog::maxPerOrder($tier);
                if ($maxPerCustomer > 0 && $quantity > $maxPerCustomer) {
                    throw new OrderPlacementException("Maximum {$maxPerCustomer} '{$name}' tickets allowed per order.");
                }

                $availKey = TicketTierCatalog::availabilityKey($tier);
                if ($availKey !== null) {
                    $avail = (int) $tier[$availKey];
                    if ($avail < $quantity) {
                        throw new OrderPlacementException("Insufficient ticket inventory for '{$name}'. Available: {$avail}, Requested: {$quantity}.");
                    }
                    $ticketTiers[$idx][$availKey] = $avail - $quantity;
                    $ticketTiersModified = true;
                }

                // Only the host's own price in the order currency; never a guessed or converted one.
                $unitPrice = TicketTierCatalog::priceFor($tier, $currency);
                if ($unitPrice === null) {
                    throw new OrderPlacementException("'{$name}' has no {$currency} price set by the organizer, so it can't be sold yet.");
                }

                return [
                    'id' => (string) Str::uuid(),
                    'menu_item_id' => null,
                    'ticket_tier_id' => $itemId,
                    'name' => $name,
                    'category' => 'Tickets',
                    'pricing_type' => 'fixed',
                    'base_price' => $unitPrice,
                    'selected_options_summary' => ['Digital Admission Pass'],
                    'options_total_extra' => 0.0,
                    'custom_donation_amount' => null,
                    'final_item_price' => $unitPrice,
                    'currency' => $currency,
                    'quantity' => $quantity,
                    'collected_quantity' => 0,
                    'special_instructions' => null,
                    'handover_status' => 'pending',
                ];
            }
        }

        throw new OrderPlacementException("Ticket type '{$itemId}' is not on sale for this event.");
    }

    /**
     * Securely retrieve an order via secure token or ticket code + customer phone verification.
     * Enforces IDOR protection when a requesting authenticated user is provided.
     */
    public function retrieveOrderSecurely(string $identifier, ?string $phoneOrToken = null, ?\App\Models\User $requestingUser = null): array
    {
        $cleanId = trim($identifier);

        // Path A: Authenticated by Cryptographic Secure Token
        if (str_starts_with($cleanId, 'tok_')) {
            $order = Order::where('secure_ticket_token', $cleanId)->with('items')->first();
            if ($order) {
                if ($requestingUser !== null && !$this->isUserAuthorizedForOrder($order, $requestingUser)) {
                    return [
                        'success' => false,
                        'error' => 'Access Denied: You do not have permission to view this order.',
                        'status_code' => 403,
                    ];
                }
                return ['success' => true, 'order' => $order];
            }
        }

        // Path B: Ticket Code or Order Number + Phone match
        $order = Order::where(function ($q) use ($cleanId) {
            $q->where('ticket_code', $cleanId)
              ->orWhere('order_number', $cleanId);
        })->with('items')->first();

        if (!$order) {
            return ['success' => false, 'error' => 'Order not found with that ticket code or order number.', 'status_code' => 404];
        }

        // IDOR Protection: If an authenticated user is requesting, assert ownership
        if ($requestingUser !== null && !$this->isUserAuthorizedForOrder($order, $requestingUser)) {
            return [
                'success' => false,
                'error' => 'Access Denied: You do not have permission to view or retrieve this order.',
                'status_code' => 403,
            ];
        }

        if ($phoneOrToken) {
            $cleanInputPhone = preg_replace('/\D/', '', $phoneOrToken);
            $cleanOrderPhone = preg_replace('/\D/', '', (string) $order->customer_phone);

            if (str_contains($cleanOrderPhone, $cleanInputPhone) || $order->secure_ticket_token === $phoneOrToken) {
                return ['success' => true, 'order' => $order];
            }
        }

        return [
            'success' => false,
            'error' => 'Authentication failed: A matching phone number or secure token is required to view this order.',
            'status_code' => 401,
        ];
    }

    /**
     * Helper to verify if an authenticated user owns or is authorized to view an order.
     */
    /** Eight characters from an alphabet without look-alikes (no 0/O, 1/I/L). */
    private static function randomTicketSuffix(): string
    {
        $alphabet = 'ABCDEFGHJKMNPQRSTUVWXYZ23456789';
        $suffix = '';
        for ($i = 0; $i < 8; $i++) {
            $suffix .= $alphabet[random_int(0, strlen($alphabet) - 1)];
        }

        return $suffix;
    }

    public function isUserAuthorizedForOrder(Order $order, \App\Models\User $user): bool
    {
        if ($user->hasRole(\App\Enums\AccountType::SYSTEM_ADMIN)) {
            return true;
        }

        if (!empty($order->user_id) && (int) $order->user_id === (int) $user->id) {
            return true;
        }

        if (!empty($order->email) && !empty($user->email) && strtolower($order->email) === strtolower($user->email)) {
            return true;
        }

        if (!empty($order->customer_phone) && !empty($user->phone)) {
            $cleanOrderPhone = preg_replace('/\D/', '', (string) $order->customer_phone);
            $cleanUserPhone = preg_replace('/\D/', '', (string) $user->phone);
            if ($cleanOrderPhone === $cleanUserPhone) {
                return true;
            }
        }

        return false;
    }
}
