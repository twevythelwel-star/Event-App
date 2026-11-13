<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Models\Event;
use App\Models\MenuItem;
use App\Models\Order;
use App\Services\Checkout\CheckoutCart;
use App\Services\Checkout\CustomerPaymentService;
use App\Services\Checkout\OrderQuoteService;
use App\Services\Checkout\PaymentOptions;
use App\Services\Checkout\TicketTierCatalog;
use App\Services\OrderPlacementException;
use App\Services\OrderPlacementService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

/**
 * Customer ordering in Blade: tickets → food → checkout → ticket.
 * The session cart holds choices only; every page re-prices it with OrderQuoteService and the
 * order itself is placed (and re-priced, stock-locked) by OrderPlacementService.
 */
class CheckoutController extends Controller
{
    public function __construct(
        private readonly OrderQuoteService $quotes,
        private readonly OrderPlacementService $orders,
    ) {
    }

    public function tickets(Request $request, Event $event): View|RedirectResponse
    {
        if ($blocked = $this->blocked($event)) {
            return $blocked;
        }
        $tiers = TicketTierCatalog::sellable($event, $request->user()->id);
        if ($tiers === []) {
            return redirect()->route('checkout.food', $event);
        }
        // Tickets held for this buyer (while checking out, or offered from the waitlist) and their waitlist places.
        $holds = \App\Services\Checkout\TicketInventory::holdsOf($event, $request->user()->id);
        $waitlist = \App\Models\WaitlistEntry::where('event_id', $event->id)->where('user_id', $request->user()->id)
            ->whereIn('status', ['waiting', 'offered'])->get()->keyBy('ticket_tier_id');

        $seating = app(\App\Services\Events\SeatingService::class);
        $seatSelection = $seating->seatSelectionOpen($event);

        return view('checkout.tickets', [
            'event' => $event,
            'tiers' => $tiers,
            'seatSelection' => $seatSelection,
            'seatMap' => $seatSelection ? $seating->seatMap($event) : collect(),
            'seatedTiers' => $seatSelection ? $seating->seatedTierIds($event) : [],
            'venue' => $seatSelection ? $seating->layout($event) : null,
            'venueAreas' => $seatSelection ? $seating->areas($event) : collect(),
            'cart' => $this->cart($request)->get($event),
            'steps' => $this->steps($event),
            'holds' => $holds,
            'waitlist' => $waitlist,
            'holdMinutes' => \App\Services\Checkout\TicketInventory::holdMinutes(),
        ]);
    }

    /** Joins the waitlist for a sold-out ticket type. */
    public function joinWaitlist(Request $request, Event $event): RedirectResponse
    {
        $data = $request->validate(['tier_id' => ['required', 'string', 'max:64'], 'quantity' => ['required', 'integer', 'min:1', 'max:20']]);
        try {
            app(\App\Services\Checkout\WaitlistService::class)->join($request->user(), $event, $data['tier_id'], (int) $data['quantity']);
        } catch (OrderPlacementException $e) {
            return back()->with('error', $e->getMessage());
        }

        return back()->with('status', "You're on the waitlist. If tickets come free, they are held for you and you get a notice in EventBite.");
    }

    public function leaveWaitlist(Request $request, Event $event, string $entryId): RedirectResponse
    {
        app(\App\Services\Checkout\WaitlistService::class)->leave($request->user(), $entryId);

        return back()->with('status', 'You left the waitlist.');
    }

    public function saveTickets(Request $request, Event $event): RedirectResponse
    {
        if ($blocked = $this->blocked($event)) {
            return $blocked;
        }
        $data = $request->validate([
            'tiers' => ['nullable', 'array'],
            'tiers.*' => ['integer', 'min:0', 'max:100'],
            'seats' => ['nullable', 'array', 'max:100'],
            'seats.*' => ['string', 'max:64'],
        ]);

        $sellable = collect(TicketTierCatalog::sellable($event, $request->user()->id))->keyBy('id');
        $chosen = [];
        foreach ($data['tiers'] ?? [] as $tierId => $quantity) {
            if ((int) $quantity > 0 && $sellable->has((string) $tierId)) {
                $chosen[(string) $tierId] = (int) $quantity;
            }
        }
        // Seated ticket types need one free seat per ticket; checked now, and again under a lock when the order is placed.
        $seating = app(\App\Services\Events\SeatingService::class);
        $seats = [];
        if ($seating->seatSelectionOpen($event)) {
            try {
                $seats = array_merge(...array_values($seating->validateChoice($event, $chosen, (array) ($data['seats'] ?? [])))) ?: [];
            } catch (OrderPlacementException $e) {
                return back()->withInput()->withErrors(['seats' => $e->getMessage()]);
            }
        }
        // Hold the chosen tickets for a few minutes so they are not sold to someone else while the buyer finishes.
        try {
            \App\Services\Checkout\TicketInventory::hold($event, $request->user()->id, $chosen);
        } catch (OrderPlacementException $e) {
            return back()->withInput()->with('error', $e->getMessage());
        }
        $this->cart($request)->setTiers($event, $chosen);
        $this->cart($request)->setSeats($event, $seats);

        return redirect()->route($this->hasFood($event) ? 'checkout.food' : 'checkout.review', $event);
    }

    public function food(Request $request, Event $event): View|RedirectResponse
    {
        if ($blocked = $this->blocked($event)) {
            return $blocked;
        }
        if (!$this->hasFood($event)) {
            return redirect()->route('checkout.review', $event);
        }

        $menu = $this->menu($event);

        return view('checkout.food', [
            'event' => $event,
            'menu' => $menu->reject(fn (MenuItem $item) => $item->isPackage())->values(),
            'packages' => $menu->filter(fn (MenuItem $item) => $item->isPackage())->values(),
            'menuById' => MenuItem::withoutGlobalScopes()->where('event_id', $event->id)->whereNull('deleted_at')->get()->keyBy('id'),
            'windows' => $event->pickupWindows(),
            'cart' => $this->cart($request)->get($event),
            'currency' => $event->currency ?: 'USD',
            'steps' => $this->steps($event),
        ]);
    }

    public function saveFood(Request $request, Event $event): RedirectResponse
    {
        if ($blocked = $this->blocked($event)) {
            return $blocked;
        }
        $data = $request->validate([
            'items' => ['nullable', 'array'],
            'items.*.quantity' => ['nullable', 'integer', 'min:0', 'max:20'],
            'items.*.choices' => ['nullable', 'array'],
            'items.*.choices.*' => ['integer', 'min:0'],
            'items.*.amount' => ['nullable', 'numeric', 'min:0', 'max:1000000'],
            'items.*.choices.*.*' => ['integer', 'min:0'],
            'items.*.instructions' => ['nullable', 'string', 'max:300'],
            'packages' => ['nullable', 'array', 'max:30'],
            'packages.*.menu_item_id' => ['required', 'string', 'max:64'],
            'packages.*.quantity' => ['nullable', 'integer', 'min:0', 'max:20'],
            'packages.*.substitutions' => ['nullable', 'array'],
            'packages.*.substitutions.*' => ['nullable', 'string', 'max:64'],
            'packages.*.choices' => ['nullable', 'array'],
            'packages.*.choices.*' => ['nullable', 'array'],
            'packages.*.choices.*.*' => ['nullable', 'string', 'max:64'],
            'packages.*.add_ons' => ['nullable', 'array'],
            'packages.*.add_ons.*' => ['nullable', 'integer', 'min:0', 'max:20'],
            'packages.*.instructions' => ['nullable', 'string', 'max:300'],
            'food.method' => ['nullable', 'in:pickup,delivery'],
            'food.pickup_window' => ['nullable', 'string', 'max:11'],
            'food.delivery_location' => ['nullable', 'string', 'max:160'],
        ]);

        $menu = $this->menu($event)->keyBy('id');
        $chosen = [];
        foreach ($data['items'] ?? [] as $itemId => $entry) {
            if ((int) ($entry['quantity'] ?? 0) > 0 && $menu->has((string) $itemId) && !$menu->get((string) $itemId)->isPackage()) {
                $chosen[(string) $itemId] = array_filter([
                    'quantity' => (int) $entry['quantity'],
                    'choices' => $entry['choices'] ?? null,
                    'instructions' => trim((string) ($entry['instructions'] ?? '')) ?: null,
                    // Kept as the text the customer typed; parsed exactly (never as a float) when priced.
                    'amount' => isset($entry['amount']) && $entry['amount'] !== '' ? trim((string) $entry['amount']) : null,
                ], fn ($v) => $v !== null);
            }
        }
        $this->cart($request)->setItems($event, $chosen);
        // Each package row keeps its own customisation, so two differently built dinners stay two lines.
        $packages = [];
        foreach ($data['packages'] ?? [] as $entry) {
            $package = $menu->get((string) $entry['menu_item_id']);
            if (!$package || !$package->isPackage() || (int) ($entry['quantity'] ?? 0) <= 0) {
                continue;
            }
            $packages[] = [
                'menu_item_id' => $package->id,
                'quantity' => (int) $entry['quantity'],
                'selection' => [
                    'substitutions' => array_filter((array) ($entry['substitutions'] ?? []), fn ($v) => $v !== null && $v !== ''),
                    'choices' => array_map(fn ($ids) => array_values(array_filter((array) $ids)), (array) ($entry['choices'] ?? [])),
                    'add_ons' => array_filter(array_map('intval', (array) ($entry['add_ons'] ?? [])), fn (int $qty) => $qty > 0),
                ],
                'instructions' => trim((string) ($entry['instructions'] ?? '')) ?: null,
            ];
        }
        $this->cart($request)->setPackages($event, $packages);
        $this->cart($request)->setFood($event, array_filter([
            'method' => $data['food']['method'] ?? 'pickup',
            'pickup_window' => $data['food']['pickup_window'] ?? null,
            'delivery_location' => trim((string) ($data['food']['delivery_location'] ?? '')) ?: null,
        ], fn ($v) => $v !== null));

        return redirect()->route('checkout.review', $event);
    }

    public function review(Request $request, Event $event): View|RedirectResponse
    {
        if ($blocked = $this->blocked($event)) {
            return $blocked;
        }
        $cart = $this->cart($request);
        if (!$cart->exists($event)) {
            return redirect()->route('checkout.tickets', $event)
                ->with('error', 'Your order is empty. It may have expired; choose your tickets or food again.');
        }

        return view('checkout.review', [
            'event' => $event,
            'quote' => $this->quotes->quote($event, $cart->get($event), $request->user()->id),
            'holds' => \App\Services\Checkout\TicketInventory::holdsOf($event, $request->user()->id),
            'methods' => PaymentOptions::forEvent($event),
            'steps' => $this->steps($event),
            'backRoute' => $this->hasFood($event) ? 'checkout.food' : 'checkout.tickets',
        ]);
    }

    /** Applies a discount code to the buyer's order (checked now, and again when the order is placed). */
    public function applyCode(Request $request, Event $event): RedirectResponse
    {
        $data = $request->validate(['discount_code' => ['required', 'string', 'max:40']]);
        $cart = $this->cart($request);
        $contents = $cart->get($event);
        $lines = $this->quotes->quote($event, array_diff_key($contents, ['discount_code' => true]), $request->user()->id)['lines'];
        try {
            $discount = \App\Services\Checkout\DiscountService::find($event, $data['discount_code'], $lines, $request->user()->id);
        } catch (OrderPlacementException $e) {
            return back()->withErrors(['discount_code' => $e->getMessage()]);
        }
        $cart->setDiscountCode($event, $discount->code);

        return back()->with('status', "Code {$discount->code} applied: " . $discount->summary($event->currency ?: 'USD') . '.');
    }

    public function removeCode(Request $request, Event $event): RedirectResponse
    {
        $this->cart($request)->setDiscountCode($event, null);

        return back()->with('status', 'Code removed.');
    }

    public function place(Request $request, Event $event): RedirectResponse
    {
        if ($blocked = $this->blocked($event)) {
            return $blocked;
        }
        $methods = array_column(PaymentOptions::forEvent($event), 'value');
        $data = $request->validate([
            'customer_name' => ['nullable', 'string', 'min:2', 'max:120'],
            'customer_phone' => ['required', 'string', 'min:7', 'max:30'],
            'method' => ['required', Rule::in($methods)],
            'payment_reference' => ['nullable', 'string', 'max:100'],
            'sender_name' => ['nullable', 'string', 'max:120'],
            'quoted_total_minor' => ['required', 'integer'],
        ], [
            'method.in' => 'Choose one of the payment methods shown.',
            'method.required' => 'Choose how you will pay.',
        ]);

        $cart = $this->cart($request);
        if (!$cart->exists($event)) {
            return redirect()->route('checkout.tickets', $event)
                ->with('error', 'Your order expired before it was placed. Choose your tickets or food again.');
        }
        $contents = $cart->get($event);
        $quote = $this->quotes->quote($event, $contents, $request->user()->id);

        if ($quote['problems'] !== []) {
            return back()->withInput()->with('error', implode(' ', $quote['problems']));
        }
        // The customer must agree to the amount they saw; if anything changed, show the new total first.
        if ($quote['total_minor'] !== (int) $data['quoted_total_minor']) {
            return back()->withInput()->with('error', 'Prices changed since you opened this page. Check the new total and place the order again.');
        }

        $result = $this->orders->placeOrder($event, [
            'customer_name' => trim((string) ($data['customer_name'] ?? '')) ?: $request->user()->name,
            'customer_phone' => $data['customer_phone'],
            'email' => $request->user()->email,
            'method' => $data['method'],
            'payment_reference' => $data['payment_reference'] ?? null,
            'sender_name' => $data['sender_name'] ?? null,
            'items' => $this->quotes->toOrderItems($contents),
            'food' => (array) ($contents['food'] ?? []),
            'idempotency_key' => $contents['key'],
            'seats' => (array) ($contents['seats'] ?? []),
            'discount_code' => $contents['discount_code'] ?? null,
            // The promoter whose link brought the buyer to this event (remembered for this session).
            'affiliate_id' => $request->session()->get("affiliate.{$event->id}"),
        ], $request->user());

        if (!$result['success']) {
            return back()->withInput()->with('error', $result['error']);
        }

        $cart->clear($event);

        return redirect()->route('tickets.show', $result['order'])
            ->with('success', "Order {$result['order']->order_number} placed.");
    }

    public function ticket(Request $request, Order $order): View
    {
        // Someone else's order is "not found", not "forbidden": don't confirm it exists.
        abort_unless($this->orders->isUserAuthorizedForOrder($order, $request->user()), 404);

        $order->load(['items', 'charges', 'event' => fn ($q) => $q->withoutGlobalScopes()->with('mediaItems')]);

        return view('checkout.ticket', [
            'order' => $order,
            'event' => $order->event,
            'instructions' => $order->event ? PaymentOptions::instructionsFor($order->event, (string) $order->method) : null,
            'payTo' => $order->event ? PaymentOptions::handleFor($order->event, (string) $order->method) : null,
        ]);
    }

    public function submitReference(Request $request, Order $order, CustomerPaymentService $payments): RedirectResponse
    {
        abort_unless($this->orders->isUserAuthorizedForOrder($order, $request->user()), 404);
        $data = $request->validate([
            'payment_reference' => ['required', 'string', 'min:3', 'max:100'],
            'sender_name' => ['nullable', 'string', 'max:120'],
        ], ['payment_reference.required' => 'Enter the confirmation number from Cash App or Zelle.']);

        try {
            $payments->submitReference($request->user(), $order, $data['payment_reference'], $data['sender_name'] ?? null);
        } catch (OrderPlacementException $e) {
            return back()->with('error', $e->getMessage());
        }

        return back()->with('success', 'Thanks. Your ticket now shows Confirmation submitted until the organizer checks the payment.');
    }

    /**
     * Controlled QR replacement / reissue: generates a new 256-bit token + SHA-256 hash and invalidates the old QR.
     */
    public function reissueTicket(Request $request, Order $order): RedirectResponse
    {
        $user = $request->user();
        $canManage = app(\App\Services\OrderOperationsService::class)->capabilities($user, $order)['verify'] ?? false;
        abort_unless($this->orders->isUserAuthorizedForOrder($order, $user) || $canManage, 404);

        $data = $request->validate([
            'reason' => ['nullable', 'string', 'max:200'],
        ]);

        try {
            $order->reissueTicketToken($user, trim((string) ($data['reason'] ?? '')) ?: 'Ticket holder requested QR replacement');
        } catch (OrderPlacementException $e) {
            return back()->with('error', $e->getMessage());
        }

        return back()->with('success', 'Your ticket QR code has been replaced. Any previous screenshots or copies of the old QR code are now permanently deactivated.');
    }

    /**
     * Immediate server-side ticket QR revocation by the event host or system administrator.
     */
    public function revokeTicket(Request $request, Order $order): RedirectResponse
    {
        $user = $request->user();
        $canManage = app(\App\Services\OrderOperationsService::class)->capabilities($user, $order)['verify'] ?? false;
        abort_unless($canManage, 403);

        $data = $request->validate([
            'reason' => ['nullable', 'string', 'max:200'],
        ]);

        $order->revokeTicketToken($user, trim((string) ($data['reason'] ?? '')) ?: 'Revoked by event organizer');

        return back()->with('success', 'Ticket QR authorization has been revoked on the server.');
    }

    /** Stop the flow for events that can't take orders, with the reason. */
    private function blocked(Event $event): ?RedirectResponse
    {
        if ($event->status !== 'published') {
            abort(404);
        }
        if (!$event->is_ordering_open) {
            return redirect()->route('events.show', $event->id)->with('error', 'The organizer has paused ordering for this event.');
        }
        // Food closing (its preorder deadline or service hours) leaves tickets on sale, and the other way round.
        if (TicketTierCatalog::sellable($event) !== [] && !$this->hasFood($event)
            && !collect(TicketTierCatalog::sellable($event))->contains(fn ($tier) => $tier['on_sale'])
            && $this->menu($event)->isNotEmpty()) {
            return redirect()->route('events.show', $event->id)->with('error', $event->foodOrderingState()['reason'] ?? 'Nothing can be ordered for this event right now.');
        }
        if (TicketTierCatalog::sellable($event) === [] && !$this->hasFood($event)) {
            return redirect()->route('events.show', $event->id)->with('error', 'Nothing is on sale for this event yet.');
        }

        return null;
    }

    /** Food can be ordered now: the host takes food orders, the food plan is open, and something is available. */
    private function hasFood(Event $event): bool
    {
        return $event->supports_food_ordering !== false
            && $event->foodOrderingState()['open']
            && $this->menu($event)->isNotEmpty();
    }

    /** Active menu items and packages inside their availability window, featured first. */
    private function menu(Event $event)
    {
        return MenuItem::withoutGlobalScopes()->where('event_id', $event->id)->where('is_active', true)
            ->orderByDesc('is_featured')->orderBy('sort_order')->orderBy('name')->get()
            ->filter(fn (MenuItem $item) => $item->isAvailableAt())->values();
    }

    /** @return list<array{label: string, route: ?string}> */
    private function steps(Event $event): array
    {
        $steps = [];
        if (TicketTierCatalog::sellable($event) !== []) {
            $steps[] = ['label' => 'Tickets', 'route' => 'checkout.tickets'];
        }
        if ($this->hasFood($event)) {
            $steps[] = ['label' => 'Food', 'route' => 'checkout.food'];
        }
        $steps[] = ['label' => 'Checkout', 'route' => 'checkout.review'];

        return $steps;
    }

    private function cart(Request $request): CheckoutCart
    {
        return new CheckoutCart($request->session());
    }
}
