<?php

declare(strict_types=1);

namespace App\Services\Checkout;

use App\Models\Event;
use Illuminate\Contracts\Session\Session;
use Illuminate\Support\Str;

/**
 * The customer's in-progress order for one event, kept in their session between the ticket, food
 * and checkout steps. It holds choices only (ids and quantities); prices are always recomputed.
 * Each cart carries one idempotency key, so resubmitting checkout returns the same order.
 */
final class CheckoutCart
{
    public function __construct(private readonly Session $session)
    {
    }

    /** @return array{tiers: array<string, int>, items: array<string, array>, key: string} */
    public function get(Event $event): array
    {
        $cart = $this->session->get($this->sessionKey($event));

        if (!is_array($cart) || !isset($cart['key'])) {
            $cart = ['tiers' => [], 'items' => [], 'key' => (string) Str::uuid()];
            $this->session->put($this->sessionKey($event), $cart);
        }

        return $cart;
    }

    public function exists(Event $event): bool
    {
        $cart = $this->session->get($this->sessionKey($event));

        return is_array($cart) && (($cart['tiers'] ?? []) !== [] || ($cart['items'] ?? []) !== []);
    }

    /** @param array<string, int> $tiers */
    public function setTiers(Event $event, array $tiers): void
    {
        $cart = $this->get($event);
        $cart['tiers'] = array_filter(array_map('intval', $tiers), fn (int $qty) => $qty > 0);
        $this->session->put($this->sessionKey($event), $cart);
    }

    /** @param array<string, array> $items */
    public function setItems(Event $event, array $items): void
    {
        $cart = $this->get($event);
        $cart['items'] = array_filter($items, fn (array $entry) => (int) ($entry['quantity'] ?? 0) > 0);
        $this->session->put($this->sessionKey($event), $cart);
    }

    public function clear(Event $event): void
    {
        $this->session->forget($this->sessionKey($event));
    }

    private function sessionKey(Event $event): string
    {
        return 'checkout.' . $event->id;
    }
}
