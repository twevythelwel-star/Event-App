<?php

declare(strict_types=1);

namespace App\Services\Checkout;

use App\Models\Affiliate;
use App\Models\DiscountCode;
use App\Models\Event;
use App\Models\MenuItem;
use App\Models\Order;
use App\Models\User;
use App\Services\AuditLogService;
use App\Services\EventManagementService;
use App\Support\Money;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

/**
 * The host's ticketing settings: smarter pricing per ticket type (price steps, early-bird, limited releases, group
 * prices, bundles), discount codes, promoters (affiliates), and whether tickets can be transferred or waitlisted.
 * Every action requires managing the event (EventManagementService::findEvent) and is audited.
 */
final class TicketingStudioService
{
    public function __construct(private readonly EventManagementService $events) {}

    /**
     * Saves one ticket type's pricing options into its ticket_tiers entry (with the event row locked).
     *
     * @param array{price_steps?: list<array{up_to?: mixed, price?: mixed}>, early_bird_price?: mixed, early_bird_ends?: ?string,
     *              releases?: list<array{quantity?: mixed, opens?: ?string}>, group_discounts?: list<array{min_quantity?: mixed, percent?: mixed}>,
     *              bundle_items?: list<array{menu_item_id?: ?string, quantity?: mixed}>} $data local times in the event's timezone
     */
    public function saveTierPricing(User $user, string $eventId, string $tierId, array $data): void
    {
        $event = $this->events->findEvent($user, $eventId);
        $tz = $event->timezone ?: config('app.timezone');
        $currency = (string) ($event->currency ?: 'USD');

        DB::transaction(function () use ($user, $event, $tierId, $data, $tz, $currency) {
            $locked = Event::withoutGlobalScopes()->whereKey($event->id)->lockForUpdate()->firstOrFail();
            $tiers = is_array($locked->ticket_tiers) ? $locked->ticket_tiers : [];
            $index = collect($tiers)->search(fn ($t) => (string) ($t['id'] ?? '') === $tierId);
            if ($index === false) {
                throw new \InvalidArgumentException('That ticket type is not part of this event.');
            }
            $tier = $tiers[$index];
            $regular = TicketTierCatalog::priceMinorFor($tier, $currency);

            // Price steps by tickets sold: each step's "up to" must be higher than the last.
            $steps = [];
            $last = 0;
            foreach ((array) ($data['price_steps'] ?? []) as $step) {
                if (!filled($step['up_to'] ?? null) && !filled($step['price'] ?? null)) {
                    continue;
                }
                $upTo = (int) ($step['up_to'] ?? 0);
                if ($upTo <= $last || !is_numeric($step['price'] ?? null) || (float) $step['price'] < 0) {
                    throw new \InvalidArgumentException('Each price step needs a price and a ticket count higher than the step before it.');
                }
                $steps[] = ['up_to' => $upTo, 'price_minor' => Money::parse((string) $step['price'], $currency)->minor()];
                $last = $upTo;
            }

            $early = null;
            $earlyEnds = null;
            if (filled($data['early_bird_price'] ?? null) || filled($data['early_bird_ends'] ?? null)) {
                if (!filled($data['early_bird_price'] ?? null) || !filled($data['early_bird_ends'] ?? null)) {
                    throw new \InvalidArgumentException('An early-bird price needs both a price and the date it ends.');
                }
                $early = Money::parse((string) $data['early_bird_price'], $currency)->minor();
                if ($regular !== null && $early >= $regular) {
                    throw new \InvalidArgumentException('The early-bird price must be lower than the regular price.');
                }
                $earlyEnds = Carbon::parse((string) $data['early_bird_ends'], $tz)->utc()->toIso8601String();
            }

            $releases = [];
            foreach ((array) ($data['releases'] ?? []) as $release) {
                if (!filled($release['quantity'] ?? null)) {
                    continue;
                }
                $releases[] = ['quantity' => max(1, min(100000, (int) $release['quantity'])),
                    'opens_iso' => filled($release['opens'] ?? null) ? Carbon::parse((string) $release['opens'], $tz)->utc()->toIso8601String() : null];
            }

            $groups = [];
            foreach ((array) ($data['group_discounts'] ?? []) as $rule) {
                if (!filled($rule['min_quantity'] ?? null)) {
                    continue;
                }
                $min = (int) $rule['min_quantity'];
                $percent = (int) ($rule['percent'] ?? 0);
                if ($min < 2 || $percent < 1 || $percent > 90) {
                    throw new \InvalidArgumentException('A group price needs at least 2 tickets and 1–90% off.');
                }
                $groups[] = ['min_quantity' => $min, 'percent' => $percent];
            }

            $bundle = [];
            $menuIds = MenuItem::withoutGlobalScopes()->where('event_id', $locked->id)->whereNull('deleted_at')->where('item_type', '!=', 'package')->pluck('id')->map(fn ($id) => (string) $id)->all();
            foreach ((array) ($data['bundle_items'] ?? []) as $item) {
                $id = (string) ($item['menu_item_id'] ?? '');
                if ($id === '') {
                    continue;
                }
                if (!in_array($id, $menuIds, true)) {
                    throw new \InvalidArgumentException('Bundles can include only this event\'s own menu items.');
                }
                $bundle[] = ['menu_item_id' => $id, 'quantity' => max(1, min(10, (int) ($item['quantity'] ?? 1)))];
            }

            $tiers[$index] = array_merge($tier, [
                'price_steps' => $steps, 'early_bird_price_minor' => $early, 'early_bird_ends_iso' => $earlyEnds,
                'releases' => $releases, 'group_discounts' => $groups, 'bundle_items' => $bundle,
            ]);
            $locked->ticket_tiers = $tiers;
            $locked->save();
            $this->audit($user, $locked, 'ticket_pricing_saved', 'events', (string) $locked->id, 'pricing for ' . ($tier['name'] ?? 'a ticket type'));
        });
    }

    public function saveSettings(User $user, string $eventId, bool $transfers, bool $waitlist): void
    {
        $event = $this->events->findEvent($user, $eventId);
        $event->forceFill(['allow_ticket_transfers' => $transfers, 'waitlist_enabled' => $waitlist])->save();
        $this->audit($user, $event, 'ticketing_settings_saved', 'events', (string) $event->id, 'transfers ' . ($transfers ? 'on' : 'off') . ', waitlist ' . ($waitlist ? 'on' : 'off'));
    }

    // ---- Discount codes -------------------------------------------------------------------------------------------

    public function saveCode(User $user, string $eventId, ?string $codeId, array $data): DiscountCode
    {
        $event = $this->events->findEvent($user, $eventId);
        $tz = $event->timezone ?: config('app.timezone');
        $code = DiscountService::normalize((string) ($data['code'] ?? ''));
        if (mb_strlen($code) < 3) {
            throw new \InvalidArgumentException('Codes need at least 3 letters or numbers.');
        }
        if (DiscountCode::where('event_id', $event->id)->where('code', $code)->when($codeId, fn ($q) => $q->whereKeyNot($codeId))->exists()) {
            throw new \InvalidArgumentException("This event already has the code {$code}.");
        }
        $kind = ($data['kind'] ?? '') === 'fixed' ? 'fixed' : 'percent';
        $amount = $kind === 'percent' ? (int) ($data['amount'] ?? 0) : Money::parse((string) ($data['amount'] ?? 0), (string) ($event->currency ?: 'USD'))->minor();
        if ($amount <= 0 || ($kind === 'percent' && $amount > 100)) {
            throw new \InvalidArgumentException('A code takes 1–100% off, or a fixed amount above zero.');
        }
        $tierIds = collect(is_array($event->ticket_tiers) ? $event->ticket_tiers : [])->pluck('id')->map(fn ($id) => (string) $id)->all();
        $restrict = array_values(array_intersect(array_map('strval', (array) ($data['ticket_tier_ids'] ?? [])), $tierIds));
        $starts = filled($data['starts_at'] ?? null) ? Carbon::parse((string) $data['starts_at'], $tz)->utc() : null;
        $ends = filled($data['ends_at'] ?? null) ? Carbon::parse((string) $data['ends_at'], $tz)->utc() : null;
        if ($starts && $ends && $ends->lessThanOrEqualTo($starts)) {
            throw new \InvalidArgumentException('A code must end after it starts.');
        }
        $affiliate = filled($data['affiliate_id'] ?? null) ? Affiliate::where('event_id', $event->id)->find((string) $data['affiliate_id']) : null;

        $discount = $codeId ? DiscountCode::where('event_id', $event->id)->findOrFail($codeId) : new DiscountCode(['event_id' => $event->id]);
        $discount->fill([
            'code' => $code, 'description' => filled($data['description'] ?? null) ? mb_substr(trim((string) $data['description']), 0, 200) : null,
            'kind' => $kind, 'amount' => $amount, 'applies_to' => in_array($data['applies_to'] ?? 'tickets', ['tickets', 'food', 'all'], true) ? $data['applies_to'] : 'tickets',
            'ticket_tier_ids' => $restrict ?: null, 'min_tickets' => filled($data['min_tickets'] ?? null) ? max(1, (int) $data['min_tickets']) : null,
            'max_uses' => filled($data['max_uses'] ?? null) ? max(1, (int) $data['max_uses']) : null,
            'max_uses_per_customer' => filled($data['max_uses_per_customer'] ?? null) ? max(1, (int) $data['max_uses_per_customer']) : null,
            'starts_at' => $starts, 'ends_at' => $ends, 'affiliate_id' => $affiliate?->id, 'is_active' => (bool) ($data['is_active'] ?? true),
        ])->save();
        $this->audit($user, $event, $codeId ? 'discount_code_updated' : 'discount_code_added', 'discount_codes', $discount->id, "code {$code}");

        return $discount;
    }

    public function deleteCode(User $user, string $eventId, string $codeId): void
    {
        $event = $this->events->findEvent($user, $eventId);
        $code = DiscountCode::where('event_id', $event->id)->findOrFail($codeId);
        if ($code->redemptions()->exists()) {
            throw new \InvalidArgumentException("{$code->code} has been used, so it is kept for the records. Turn it off instead.");
        }
        $code->delete();
        $this->audit($user, $event, 'discount_code_removed', 'discount_codes', $codeId, "code {$code->code} removed");
    }

    // ---- Affiliates -----------------------------------------------------------------------------------------------

    public function saveAffiliate(User $user, string $eventId, ?string $affiliateId, array $data): Affiliate
    {
        $event = $this->events->findEvent($user, $eventId);
        $code = DiscountService::normalize((string) ($data['code'] ?? ''));
        if (trim((string) ($data['name'] ?? '')) === '' || mb_strlen($code) < 3) {
            throw new \InvalidArgumentException('A promoter needs a name and a link code of at least 3 letters or numbers.');
        }
        if (Affiliate::where('event_id', $event->id)->where('code', $code)->when($affiliateId, fn ($q) => $q->whereKeyNot($affiliateId))->exists()) {
            throw new \InvalidArgumentException("Another promoter already uses the link code {$code}.");
        }
        $percent = (float) ($data['commission_percent'] ?? 0);
        if ($percent < 0 || $percent > 50) {
            throw new \InvalidArgumentException('Commission is 0–50%.');
        }
        $affiliate = $affiliateId ? Affiliate::where('event_id', $event->id)->findOrFail($affiliateId) : new Affiliate(['event_id' => $event->id]);
        $affiliate->fill(['name' => mb_substr(trim((string) $data['name']), 0, 120), 'code' => $code,
            'email' => filled($data['email'] ?? null) ? mb_substr(trim((string) $data['email']), 0, 190) : null,
            'commission_percent' => round($percent, 2), 'is_active' => (bool) ($data['is_active'] ?? true)])->save();
        $this->audit($user, $event, 'affiliate_saved', 'affiliates', $affiliate->id, "promoter {$affiliate->name} ({$code})");

        return $affiliate;
    }

    /**
     * Sales each promoter brought in: paid orders and tickets, revenue, and the commission it earns.
     *
     * @return Collection<int, array{affiliate: Affiliate, orders: int, tickets: int, revenue_minor: int, commission_minor: int, pending_orders: int}>
     */
    public function affiliateReport(Event $event): Collection
    {
        $paid = ['paid', 'partially_refunded'];

        return Affiliate::where('event_id', $event->id)->orderBy('name')->get()->map(function (Affiliate $affiliate) use ($paid) {
            $orders = Order::withoutGlobalScopes()->where('affiliate_id', $affiliate->id)->whereIn('status', $paid)->with('items')->get();
            $revenue = (int) $orders->sum(fn (Order $o) => (int) $o->total_minor - (int) $o->refunded_minor);

            return [
                'affiliate' => $affiliate,
                'orders' => $orders->count(),
                'tickets' => (int) $orders->sum(fn (Order $o) => $o->items->whereNotNull('ticket_tier_id')->sum('quantity')),
                'revenue_minor' => $revenue,
                'commission_minor' => (int) round($revenue * (float) $affiliate->commission_percent / 100),
                'pending_orders' => Order::withoutGlobalScopes()->where('affiliate_id', $affiliate->id)->whereIn('status', ['awaiting_payment', 'payment_submitted', 'under_verification'])->count(),
            ];
        });
    }

    private function audit(User $user, Event $event, string $action, string $entity, string $id, string $details): void
    {
        AuditLogService::log(action: $action, targetEntity: $entity, targetId: $id, details: "{$event->title}: {$details}.",
            actor: $user, organizerId: (string) $event->organizer_id, eventId: (string) $event->id);
    }
}
