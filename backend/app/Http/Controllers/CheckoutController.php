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
        $tiers = TicketTierCatalog::sellable($event);
        if ($tiers === []) {
            return redirect()->route('checkout.food', $event);
        }

        return view('checkout.tickets', [
            'event' => $event,
            'tiers' => $tiers,
            'cart' => $this->cart($request)->get($event),
            'steps' => $this->steps($event),
        ]);
    }

    public function saveTickets(Request $request, Event $event): RedirectResponse
    {
        if ($blocked = $this->blocked($event)) {
            return $blocked;
        }
        $data = $request->validate([
            'tiers' => ['nullable', 'array'],
            'tiers.*' => ['integer', 'min:0', 'max:100'],
        ]);

        $sellable = collect(TicketTierCatalog::sellable($event))->keyBy('id');
        $chosen = [];
        foreach ($data['tiers'] ?? [] as $tierId => $quantity) {
            if ((int) $quantity > 0 && $sellable->has((string) $tierId)) {
                $chosen[(string) $tierId] = (int) $quantity;
            }
        }
        $this->cart($request)->setTiers($event, $chosen);

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

        return view('checkout.food', [
            'event' => $event,
            'menu' => $this->menu($event),
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
        ]);

        $menu = $this->menu($event)->keyBy('id');
        $chosen = [];
        foreach ($data['items'] ?? [] as $itemId => $entry) {
            if ((int) ($entry['quantity'] ?? 0) > 0 && $menu->has((string) $itemId)) {
                $chosen[(string) $itemId] = array_filter([
                    'quantity' => (int) $entry['quantity'],
                    'choices' => $entry['choices'] ?? null,
                    // Kept as the text the customer typed; parsed exactly (never as a float) when priced.
                    'amount' => isset($entry['amount']) && $entry['amount'] !== '' ? trim((string) $entry['amount']) : null,
                ], fn ($v) => $v !== null);
            }
        }
        $this->cart($request)->setItems($event, $chosen);

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
            'quote' => $this->quotes->quote($event, $cart->get($event)),
            'methods' => PaymentOptions::forEvent($event),
            'steps' => $this->steps($event),
            'backRoute' => $this->hasFood($event) ? 'checkout.food' : 'checkout.tickets',
        ]);
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
        $quote = $this->quotes->quote($event, $contents);

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
            'idempotency_key' => $contents['key'],
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

        $order->load(['items', 'charges', 'event' => fn ($q) => $q->withoutGlobalScopes()]);

        return view('checkout.ticket', [
            'order' => $order,
            'event' => $order->event,
            'instructions' => $order->event ? PaymentOptions::instructionsFor($order->event, (string) $order->method) : null,
        ]);
    }

    public function submitReference(Request $request, Order $order, CustomerPaymentService $payments): RedirectResponse
    {
        abort_unless($this->orders->isUserAuthorizedForOrder($order, $request->user()), 404);
        $data = $request->validate([
            'payment_reference' => ['required', 'string', 'min:3', 'max:100'],
            'sender_name' => ['nullable', 'string', 'max:120'],
        ], ['payment_reference.required' => 'Enter the reference from your bank or app.']);

        try {
            $payments->submitReference($request->user(), $order, $data['payment_reference'], $data['sender_name'] ?? null);
        } catch (OrderPlacementException $e) {
            return back()->with('error', $e->getMessage());
        }

        return back()->with('success', 'Thanks. Your ticket now shows Confirmation submitted until the organizer checks the payment.');
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
        if ($event->advance_order_deadline && now()->greaterThan($event->advance_order_deadline)) {
            return redirect()->route('events.show', $event->id)->with('error', 'The ordering deadline for this event has passed.');
        }
        if (TicketTierCatalog::sellable($event) === [] && !$this->hasFood($event)) {
            return redirect()->route('events.show', $event->id)->with('error', 'Nothing is on sale for this event yet.');
        }

        return null;
    }

    private function hasFood(Event $event): bool
    {
        return MenuItem::withoutGlobalScopes()->where('event_id', $event->id)->where('is_active', true)->exists();
    }

    private function menu(Event $event)
    {
        return MenuItem::withoutGlobalScopes()->where('event_id', $event->id)->where('is_active', true)
            ->orderBy('sort_order')->orderBy('name')->get();
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
