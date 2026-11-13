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

        // When food can be ordered (the preorder deadline, event-day service hours) is checked per food line, and
        // ticket sales windows per ticket line, inside the transaction below.

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
                // Only a published event sells anything; a draft's link alone never makes it purchasable.
                if ($event->status !== 'published') {
                    throw new OrderPlacementException('This event is not on sale.');
                }
                if ($idempotencyKey !== '' && ($existing = $this->findByIdempotencyKey($event, $idempotencyKey))) {
                    return ['success' => true, 'order' => $existing];
                }
                $orderItemsToCreate = [];
                $ticketTiers = is_array($event->ticket_tiers) ? $event->ticket_tiers : [];
                $ticketTiersModified = false;
                $foodChecked = false;
                $foodGate = function () use ($event, &$foodChecked) {
                    if ($foodChecked) {
                        return;
                    }
                    $foodChecked = true;
                    if ($event->supports_food_ordering === false) {
                        throw new OrderPlacementException('This event is not taking food orders.');
                    }
                    $state = $event->foodOrderingState();
                    if (!$state['open']) {
                        throw new OrderPlacementException($state['reason']);
                    }
                };

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
                        // One line per price (a purchase crossing a price step is priced ticket by ticket), plus
                        // any food a bundle includes, reserved from stock like any other dish.
                        $soldByTier ??= \App\Services\Checkout\TicketPricing::soldByTier($event);
                        array_push($orderItemsToCreate, ...$this->resolveAndReserveTicketTier(
                            $menuItemId,
                            $quantity,
                            $currency,
                            $ticketTiers,
                            $ticketTiersModified,
                            $event,
                            $authUser?->id,
                            $soldByTier,
                        ));
                        continue;
                    }

                    if (!$menuItem) {
                        throw new OrderPlacementException("Menu item with ID {$menuItemId} not found.");
                    }
                    $foodGate();
                    if (!$menuItem->isAvailableAt()) {
                        throw new OrderPlacementException("'{$menuItem->name}' is not available to order right now.");
                    }
                    $this->assertWithinCustomerLimit($menuItem, $quantity, $event, $authUser, $orderItemsToCreate);

                    if ($menuItem->isPackage()) {
                        // A package line carries the price; its parts follow as component lines priced at zero, each
                        // reserving its own stock, so they are prepared and handed over like single items.
                        if ($menuItem->available_stock !== null) {
                            if ($menuItem->available_stock < $quantity) {
                                throw new OrderPlacementException("Only {$menuItem->available_stock} '{$menuItem->name}' left.");
                            }
                            $menuItem->decrement('available_stock', $quantity);
                        }
                        array_push($orderItemsToCreate, ...$this->packageLines($menuItem, $quantity, (array) ($entry['package'] ?? []), $currency, $event, $entry['special_instructions'] ?? null));
                        continue;
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
                    // Passed through as given (text or number) and parsed exactly; never cast to float.
                    $customDonationAmount = $entry['custom_donation_amount'] ?? null;
                    $portionSize = $entry['portion_size'] ?? null;

                    $priceResult = PriceCalculationService::calculateItemPrice(
                        $menuItem,
                        $currency,
                        $selectedChoices,
                        $customDonationAmount,
                        $portionSize
                    );

                    // Prepare historical immutable snapshot
                    $orderItemsToCreate[] = [
                        'id' => (string) Str::uuid(),
                        'menu_item_id' => $menuItem->id,
                        'name' => $menuItem->name, // Frozen snapshot of name
                        'category' => $menuItem->category, // Frozen snapshot of category
                        'pricing_type' => $menuItem->pricing_type,
                        'base_price' => \App\Support\Money::ofMinor($priceResult['base_price_minor'], $currency)->toDecimalString(), // Frozen snapshot of base price
                        'selected_options_summary' => $priceResult['selected_options_summary'], // Frozen options
                        'options_minor' => $priceResult['options_extra_minor'],
                        'custom_donation_amount' => $menuItem->pricing_type === 'custom_amount'
                            ? \App\Support\Money::ofMinor($priceResult['final_price_minor'], $currency)->toDecimalString()
                            : null,
                        'unit_price_minor' => $priceResult['final_price_minor'], // Frozen snapshot, exact
                        'currency' => $currency,
                        'quantity' => $quantity,
                        'collected_quantity' => 0,
                        'special_instructions' => $entry['special_instructions'] ?? null,
                        'vendor_assignment' => $menuItem->vendor_assignment, // Frozen snapshot of vendor assignment
                        'handover_status' => 'pending',
                        'line_type' => OrderItem::ITEM,
                    ];
                }

                if ($orderItemsToCreate === []) {
                    throw new OrderPlacementException('Your order must contain at least one ticket or food item.');
                }
                $food = $foodChecked ? $this->foodCollection($event, (array) ($payload['food'] ?? [])) : [];

                if ($ticketTiersModified) {
                    $event->ticket_tiers = $ticketTiers;
                    $event->save();
                }

                // Totals in exact integer minor units: item subtotal, then the configured discounts, fees and
                // taxes (ChargeCalculator, the same rules the checkout preview showed), then the total.
                // Package components are priced inside their package line, so they are not counted again.
                $pricedLines = array_values(array_map(fn (array $it) => [
                    'kind' => !empty($it['ticket_tier_id']) ? 'ticket' : 'food',
                    'tier_id' => $it['ticket_tier_id'] ?? null,
                    'quantity' => (int) $it['quantity'],
                    'line_total_minor' => (int) $it['unit_price_minor'] * (int) $it['quantity'],
                ], array_filter($orderItemsToCreate, fn (array $it) => ($it['line_type'] ?? OrderItem::ITEM) !== OrderItem::COMPONENT)));
                // A discount code, checked again with its row locked so a limited code is never over-used.
                $discountCode = null;
                $extraDiscounts = [];
                if (filled($payload['discount_code'] ?? null)) {
                    $discountCode = \App\Services\Checkout\DiscountService::find($event, (string) $payload['discount_code'], $pricedLines, $authUser?->id, lock: true);
                    $extraDiscounts[] = \App\Services\Checkout\DiscountService::chargeLine($discountCode, $pricedLines, $currency);
                }
                $totals = \App\Services\Pricing\OrderTotals::from(
                    $currency,
                    $pricedLines,
                    (new \App\Services\Pricing\ChargeCalculator())->charges($event, $pricedLines, $currency, $extraDiscounts),
                );

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
                $secureToken = Order::generateSecureTicketToken();

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
                    ...$totals->orderAttributes(),
                    'amount_paid_minor' => 0, // Never auto-credited without verification
                    'status' => $initialStatus,
                    'fulfillment_status' => 'unprepared',
                    'payment_reference' => $paymentRef ?: null,
                    'sender_name' => trim($payload['sender_name'] ?? '') ?: null,
                    'authorized_unpaid' => $authorizedUnpaid,
                    // Cash at pickup is paid to staff at the event, so its tickets and food are held until the host
                    // records the cash or cancels the order, not on the short timer for orders awaiting payment.
                    'reservation_expires_at' => $authorizedUnpaid ? null : now()->addMinutes(max(1, (int) config('commerce.reservation_minutes', 15))),
                    'secure_ticket_token' => $secureToken,
                    'all_handed_over' => false,
                    'idempotency_key' => $idempotencyKey !== '' ? $idempotencyKey : null,
                    'notes' => trim((string) ($payload['notes'] ?? '')) ?: null,
                    // The promoter whose link brought the buyer (only one of this event's active promoters), or the
                    // promoter a code belongs to; and the code used.
                    'affiliate_id' => $this->affiliateFor($event, $payload['affiliate_id'] ?? null, $discountCode),
                    'discount_code_id' => $discountCode?->id,
                    ...$food,
                ]);

                foreach ($orderItemsToCreate as $itemData) {
                    $itemData['order_id'] = $order->id;
                    // The line keeps the id it was given, so package components can point at their package line.
                    $line = new OrderItem($itemData);
                    $line->id = $itemData['id'];
                    $line->save();
                }

                // One admission credential per attendee, created in this transaction: if anything below fails, the
                // order and every credential roll back together; a retried submission finds them by idempotency key.
                app(\App\Services\Tickets\AttendeeTicketService::class)->issueFor($order);

                // Each fee, tax and discount line is frozen with the order; later rule changes never alter it.
                foreach ($totals->charges as $charge) {
                    \App\Models\OrderCharge::create($charge + ['order_id' => $order->id]);
                }

                if ($discountCode) {
                    $saving = (int) collect($totals->charges)->where('kind', 'discount')->where('label', "Code {$discountCode->code}")->sum('amount_minor');
                    \App\Services\Checkout\DiscountService::redeem($discountCode, $order, $saving);
                }
                // The buyer's holds have become this order; waitlist places it fills are done.
                if ($authUser) {
                    $boughtTiers = array_values(array_unique(array_filter(array_map(fn ($it) => $it['ticket_tier_id'] ?? null, $orderItemsToCreate))));
                    \App\Services\Checkout\TicketInventory::release($event, (int) $authUser->id);
                    app(\App\Services\Checkout\WaitlistService::class)->markPurchased($event, (int) $authUser->id, $boughtTiers);
                }

                // Chosen seats: locked and checked again here, inside the order's transaction, so a seat can never
                // go to two orders. A seat that was taken meanwhile fails the whole order (nothing is reserved).
                $seating = app(\App\Services\Events\SeatingService::class);
                if ($seating->seatSelectionOpen($event)) {
                    $ticketQuantities = [];
                    foreach ($orderItemsToCreate as $itemData) {
                        if (!empty($itemData['ticket_tier_id'])) {
                            $ticketQuantities[(string) $itemData['ticket_tier_id']] = ($ticketQuantities[(string) $itemData['ticket_tier_id']] ?? 0) + (int) $itemData['quantity'];
                        }
                    }
                    $seating->claim($event, $order, $ticketQuantities, array_values((array) ($payload['seats'] ?? [])));
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

    /**
     * Food lines for one meal package: the package line (it carries the whole price) and a zero-priced component
     * line per part, each reserving that item's stock.
     *
     * @return list<array>
     */
    private function packageLines(MenuItem $package, int $quantity, array $selection, string $currency, Event $event, ?string $instructions): array
    {
        $menu = MenuItem::withoutGlobalScopes()->where('event_id', $event->id)->whereNull('deleted_at')->get()->keyBy('id');
        $priced = \App\Services\Menu\MealPackage::price($package, $selection, $currency, $menu);
        $headerId = (string) Str::uuid();
        $lines = [[
            'id' => $headerId,
            'menu_item_id' => $package->id,
            'name' => $package->name,
            'category' => $package->category,
            'pricing_type' => 'package',
            'base_price' => \App\Support\Money::ofMinor($priced['base_minor'], $currency)->toDecimalString(),
            'selected_options_summary' => $priced['summary'],
            'options_minor' => $priced['extras_minor'],
            'custom_donation_amount' => null,
            'unit_price_minor' => $priced['unit_minor'],
            'currency' => $currency,
            'quantity' => $quantity,
            'collected_quantity' => 0,
            'special_instructions' => $instructions !== null ? (trim($instructions) ?: null) : null,
            'vendor_assignment' => $package->vendor_assignment,
            'handover_status' => 'pending',
            'line_type' => OrderItem::PACKAGE,
        ]];

        // Parts reserve stock together: the same item may appear twice (an included side and an extra portion).
        $needed = [];
        foreach ($priced['parts'] as $part) {
            $needed[$part['item']->id] = ($needed[$part['item']->id] ?? 0) + $part['quantity'] * $quantity;
        }
        foreach ($needed as $itemId => $units) {
            $locked = MenuItem::withoutGlobalScopes()->whereKey($itemId)->lockForUpdate()->first();
            if ($locked && $locked->available_stock !== null) {
                if ($locked->available_stock < $units) {
                    throw new OrderPlacementException("Not enough {$locked->name} left for this many '{$package->name}'. Available: {$locked->available_stock}.");
                }
                $locked->decrement('available_stock', $units);
            }
        }

        foreach ($priced['parts'] as $part) {
            $lines[] = [
                'id' => (string) Str::uuid(),
                'menu_item_id' => $part['item']->id,
                'name' => $part['label'],
                'category' => $part['item']->category,
                'pricing_type' => 'fixed',
                'base_price' => '0.00',
                'selected_options_summary' => [$package->name],
                'options_minor' => 0,
                'custom_donation_amount' => null,
                'unit_price_minor' => 0,
                'currency' => $currency,
                'quantity' => $part['quantity'] * $quantity,
                'collected_quantity' => 0,
                'special_instructions' => null,
                'vendor_assignment' => $part['item']->vendor_assignment,
                'handover_status' => 'pending',
                'line_type' => OrderItem::COMPONENT,
                'package_line_id' => $headerId,
            ];
        }

        return $lines;
    }

    /**
     * The host's per-customer limit for an item: what this order adds to what the customer already ordered at this
     * event (orders not cancelled or refunded) must stay within it.
     */
    private function assertWithinCustomerLimit(MenuItem $item, int $quantity, Event $event, ?\App\Models\User $user, array $linesSoFar): void
    {
        $limit = (int) ($item->max_per_order ?? 0);
        if ($limit <= 0) {
            return;
        }
        // Plain arrays: this service also runs under the legacy test runner, which has no collection helpers.
        $inThisOrder = array_sum(array_map(fn ($line) => (int) $line['quantity'], array_filter($linesSoFar,
            fn ($line) => ($line['menu_item_id'] ?? null) === $item->id && ($line['line_type'] ?? OrderItem::ITEM) !== OrderItem::COMPONENT)));
        $before = 0;
        if ($user) {
            $before = (int) OrderItem::query()->where('menu_item_id', $item->id)->where('line_type', '!=', OrderItem::COMPONENT)
                ->whereHas('order', fn ($q) => $q->withoutGlobalScopes()->where('event_id', $event->id)->where('user_id', $user->id)
                    ->whereNotIn('status', ['cancelled', 'refunded']))
                ->sum('quantity');
        }
        if ($before + $inThisOrder + $quantity > $limit) {
            $left = max(0, $limit - $before - $inThisOrder);
            throw new OrderPlacementException("'{$item->name}' is limited to {$limit} per customer" . ($before > 0 ? "; you can add {$left} more" : '') . '.');
        }
    }

    /**
     * How the customer collects their food: pickup (in a pickup window when the host set them, each with its own
     * order limit) or, where the host offers it, delivery to a table or area. The event row is locked, so the
     * window counts cannot race.
     *
     * @return array{food_fulfillment_method: string, pickup_window: ?string, delivery_location: ?string}
     */
    private function foodCollection(Event $event, array $choice): array
    {
        if ($event->food_max_orders) {
            $foodOrders = Order::withoutGlobalScopes()->where('event_id', $event->id)->whereNotIn('status', ['cancelled', 'refunded'])
                ->whereHas('items', fn ($q) => $q->whereNotNull('menu_item_id'))->count();
            if ($foodOrders >= $event->food_max_orders) {
                throw new OrderPlacementException('Food orders for this event are full.');
            }
        }

        $method = ($choice['method'] ?? 'pickup') === 'delivery' ? 'delivery' : 'pickup';
        if ($method === 'delivery') {
            if ($event->food_fulfillment !== 'pickup_and_delivery') {
                throw new OrderPlacementException('This event offers food pickup only.');
            }
            $where = trim((string) ($choice['delivery_location'] ?? ''));
            if (mb_strlen($where) < 2) {
                throw new OrderPlacementException('Tell us your table number or area for food delivery.');
            }

            return ['food_fulfillment_method' => 'delivery', 'pickup_window' => null, 'delivery_location' => mb_substr($where, 0, 160)];
        }

        $windows = [];
        foreach ($event->pickupWindows() as $w) {
            $windows[\App\Services\Events\EventSchedule::windowKey($w)] = $w;
        }
        if ($windows === []) {
            return ['food_fulfillment_method' => 'pickup', 'pickup_window' => null, 'delivery_location' => null];
        }
        $key = (string) ($choice['pickup_window'] ?? '');
        $window = $windows[$key] ?? null;
        if (!$window) {
            throw new OrderPlacementException('Choose a pickup time for your food.');
        }
        if (!empty($window['max_orders'])) {
            $taken = Order::withoutGlobalScopes()->where('event_id', $event->id)->where('pickup_window', $key)
                ->whereNotIn('status', ['cancelled', 'refunded'])->count();
            if ($taken >= (int) $window['max_orders']) {
                throw new OrderPlacementException("The {$window['start']}–{$window['end']} pickup time is full. Choose another.");
            }
        }

        return ['food_fulfillment_method' => 'pickup', 'pickup_window' => $key, 'delivery_location' => null];
    }

    /** The order's promoter: the link's promoter if active for this event, else the promoter a code belongs to. */
    private function affiliateFor(Event $event, mixed $affiliateId, ?\App\Models\DiscountCode $code): ?string
    {
        if (filled($affiliateId) && \App\Models\Affiliate::where('event_id', $event->id)->where('is_active', true)->whereKey((string) $affiliateId)->exists()) {
            return (string) $affiliateId;
        }

        return $code?->affiliate_id;
    }

    /**
     * Reserves tickets of one type and prices them: price steps by tickets sold (ticket by ticket across a step),
     * early-bird and group prices (TicketPricing), within the type's stock and release schedule and never into
     * tickets other buyers hold (TicketInventory). Food a bundle includes is reserved as zero-priced parts of the
     * ticket line, prepared and handed over like any dish. Called with the event row locked.
     *
     * @param array<string, int> $soldByTier
     * @return list<array> order lines
     */
    private function resolveAndReserveTicketTier(
        string $itemId,
        int $quantity,
        string $currency,
        array &$ticketTiers,
        bool &$ticketTiersModified,
        ?Event $event = null,
        ?int $userId = null,
        array &$soldByTier = [],
    ): array {
        // Match against host-configured ticket_tiers first
        foreach ($ticketTiers as $idx => $tier) {
            if (isset($tier['id']) && (string) $tier['id'] === $itemId) {
                $name = (string) ($tier['name'] ?? 'Admission Ticket');
                // The tier's own sale dates, inside the event's ticket sales period.
                $window = TicketTierCatalog::saleWindow(TicketTierCatalog::withEventSaleWindow($tier, $event));
                if ($window !== 'open') {
                    throw new OrderPlacementException($window === 'ended'
                        ? "Sales for '{$name}' have ended."
                        : "'{$name}' is not on sale yet.");
                }
                $maxPerCustomer = TicketTierCatalog::maxPerOrder($tier);
                if ($maxPerCustomer > 0 && $quantity > $maxPerCustomer) {
                    throw new OrderPlacementException("Maximum {$maxPerCustomer} '{$name}' tickets allowed per order.");
                }
                $minPerOrder = TicketTierCatalog::minPerOrder($tier);
                if ($quantity < $minPerOrder) {
                    throw new OrderPlacementException("Minimum {$minPerOrder} '{$name}' tickets required per order.");
                }
                if (($tier['isActive'] ?? true) === false || ($tier['visibility'] ?? 'public') !== 'public') {
                    throw new OrderPlacementException("'{$name}' is not available for public purchase.");
                }

                // Stock first, then the release schedule and other buyers' holds (the buyer's own holds are theirs).
                $sold = (int) ($soldByTier[$itemId] ?? 0);
                $availKey = TicketTierCatalog::availabilityKey($tier);
                if ($availKey !== null && (int) $tier[$availKey] < $quantity) {
                    throw new OrderPlacementException("Insufficient ticket inventory for '{$name}'. Available: {$tier[$availKey]}, Requested: {$quantity}.");
                }
                if ($event) {
                    $free = \App\Services\Checkout\TicketInventory::availableFor($event, $tier, $userId, $soldByTier);
                    if ($free !== null && $free < $quantity) {
                        throw new OrderPlacementException($free === 0
                            ? "'{$name}' is sold out right now." . (\App\Services\Checkout\TicketPricing::nextRelease($tier) ? ' More are released later.' : '')
                            : "Only {$free} '{$name}' can be bought right now.");
                    }
                }
                if ($availKey !== null) {
                    $ticketTiers[$idx][$availKey] = (int) $tier[$availKey] - $quantity;
                    $ticketTiersModified = true;
                }

                // Only the host's own prices in the order currency; never a guessed or converted one.
                $priced = \App\Services\Checkout\TicketPricing::lines($tier, $currency, $event, $quantity, $sold);
                if ($priced === []) {
                    throw new OrderPlacementException("'{$name}' has no {$currency} price set by the organizer, so it can't be sold yet.");
                }
                $soldByTier[$itemId] = $sold + $quantity;

                $lines = [];
                foreach ($priced as $part) {
                    $lines[] = [
                        'id' => (string) Str::uuid(),
                        'menu_item_id' => null,
                        'ticket_tier_id' => $itemId,
                        'name' => $name,
                        'category' => 'Tickets',
                        'pricing_type' => 'fixed',
                        'base_price' => \App\Support\Money::ofMinor($part['unit_price_minor'], $currency)->toDecimalString(),
                        'selected_options_summary' => array_values(array_filter(['Digital Admission Pass', $part['label'] ? ucfirst($part['label']) : null])),
                        'options_minor' => 0,
                        'custom_donation_amount' => null,
                        'unit_price_minor' => $part['unit_price_minor'],
                        'currency' => $currency,
                        'quantity' => $part['quantity'],
                        'collected_quantity' => 0,
                        'special_instructions' => null,
                        'handover_status' => 'pending',
                        'line_type' => OrderItem::TICKET,
                    ];
                }

                // A bundle: the food it includes, per ticket, reserved now and priced inside the ticket.
                foreach (\App\Services\Checkout\TicketPricing::bundleItems($tier) as $included) {
                    $dish = MenuItem::withoutGlobalScopes()->where('id', $included['menu_item_id'])->where('event_id', $event?->id)
                        ->whereNull('deleted_at')->lockForUpdate()->first();
                    if (!$dish) {
                        throw new OrderPlacementException("Food included with '{$name}' is no longer available. Contact the host.");
                    }
                    $units = $included['quantity'] * $quantity;
                    if ($dish->available_stock !== null) {
                        if ((int) $dish->available_stock < $units) {
                            throw new OrderPlacementException("'{$name}' includes {$dish->name}, and only {$dish->available_stock} are left.");
                        }
                        $dish->available_stock = (int) $dish->available_stock - $units;
                        $dish->save();
                    }
                    $lines[] = [
                        'id' => (string) Str::uuid(), 'menu_item_id' => $dish->id, 'ticket_tier_id' => null, 'name' => $dish->name,
                        'category' => (string) ($dish->category ?: 'Food'), 'pricing_type' => 'fixed', 'base_price' => '0.00',
                        'selected_options_summary' => ["Included with {$name}"], 'options_minor' => 0, 'custom_donation_amount' => null,
                        'unit_price_minor' => 0, 'currency' => $currency, 'quantity' => $units, 'collected_quantity' => 0,
                        'special_instructions' => null, 'handover_status' => 'pending', 'line_type' => OrderItem::COMPONENT,
                        'package_line_id' => $lines[0]['id'],
                    ];
                }

                return $lines;
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

        // Path A: Authenticated by Cryptographic Secure Token (verified via SHA-256 hash)
        if (str_starts_with($cleanId, 'tok_') || \App\Services\Tickets\AttendeeTicketService::looksLikeCredential($cleanId)) {
            $order = Order::whereTicketToken($cleanId)->with('items')->first();
            if ($order && !$order->isTicketRevoked()) {
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
            $tokenMatches = $order->secure_ticket_token_hash
                ? hash_equals((string) $order->secure_ticket_token_hash, Order::hashTicketToken($phoneOrToken))
                : $order->secure_ticket_token === $phoneOrToken;

            if (($cleanInputPhone !== '' && str_contains($cleanOrderPhone, $cleanInputPhone)) || $tokenMatches) {
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
