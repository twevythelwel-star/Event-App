<?php

namespace App\Services\Events;

use App\Models\Event;
use App\Models\Order;
use App\Models\User;
use App\Models\VenueArea;
use App\Models\VenueSeat;
use App\Services\AuditLogService;
use App\Services\Checkout\TicketTierCatalog;
use App\Services\EventManagementService;
use App\Services\OrderPlacementException;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

/**
 * The venue plan and seat selection.
 *
 * Hosts draw areas on a plan (plan units, 1000 × 640 by default). Tables and rows of seats generate individual seats
 * linked to the area's ticket type. When seat selection is on, buyers of those ticket types pick their seats, and the
 * order claims them inside the order's own transaction with the seat rows locked, so a seat is never sold twice.
 * A seat is taken while its order is live; an expired, cancelled or refunded order frees it without a release step.
 */
class SeatingService
{
    public const MAX_AREAS = 120;
    public const MAX_SEATS = 3000;

    public function __construct(private readonly EventManagementService $events) {}

    /** @return array{width: int, height: int, seat_selection_enabled: bool, notes: ?string} */
    public function layout(Event $event): array
    {
        $row = DB::table('venue_layouts')->where('event_id', $event->id)->first();

        return [
            'width' => (int) ($row->width ?? 1000),
            'height' => (int) ($row->height ?? 640),
            'seat_selection_enabled' => (bool) ($row->seat_selection_enabled ?? false),
            'notes' => $row->notes ?? null,
        ];
    }

    /** @return Collection<int, VenueArea> */
    public function areas(Event $event): Collection
    {
        return VenueArea::where('event_id', $event->id)->orderBy('sort_order')->get();
    }

    /**
     * Saves the whole plan. Areas keep their ids; a new area has none. Seated areas (re)generate their seats unless
     * seats are already sold, in which case their seat structure cannot change and they cannot be removed.
     *
     * @param list<array<string, mixed>> $areas
     */
    public function saveLayout(User $user, string $eventId, array $areas, bool $seatSelection, ?string $notes): void
    {
        $event = $this->events->findEvent($user, $eventId);
        $layout = $this->layout($event);
        $tierIds = collect(is_array($event->ticket_tiers) ? $event->ticket_tiers : [])->pluck('id')->map(fn ($id) => (string) $id)->all();
        if (count($areas) > self::MAX_AREAS) {
            throw new \InvalidArgumentException('A plan can have at most ' . self::MAX_AREAS . ' areas.');
        }

        $clean = [];
        $seatedLabels = [];
        $totalSeats = 0;
        foreach (array_values($areas) as $i => $area) {
            $kind = (string) ($area['kind'] ?? '');
            $label = trim((string) ($area['label'] ?? ''));
            if (!array_key_exists($kind, VenueArea::KINDS)) {
                throw new \InvalidArgumentException('Unknown area type.');
            }
            if ($label === '') {
                throw new \InvalidArgumentException('Every area needs a name.');
            }
            [$x, $y, $w, $h] = array_map(fn ($k) => (int) ($area[$k] ?? -1), ['x', 'y', 'width', 'height']);
            if ($w < 20 || $h < 20 || $x < 0 || $y < 0 || $x + $w > $layout['width'] || $y + $h > $layout['height']) {
                throw new \InvalidArgumentException("'{$label}' must sit inside the plan and be at least 20 × 20.");
            }
            $tier = filled($area['ticket_tier_id'] ?? null) ? (string) $area['ticket_tier_id'] : null;
            if ($tier !== null && (!in_array($kind, VenueArea::TICKETED, true) || !in_array($tier, $tierIds, true))) {
                throw new \InvalidArgumentException("'{$label}' links to a ticket type this event does not have.");
            }
            $row = ['id' => filled($area['id'] ?? null) ? (string) $area['id'] : null, 'kind' => $kind, 'label' => mb_substr($label, 0, 60),
                'x' => $x, 'y' => $y, 'width' => $w, 'height' => $h, 'ticket_tier_id' => $tier, 'capacity' => null, 'rows' => null,
                'seats_per_row' => null, 'seats_per_table' => null, 'is_accessible' => (bool) ($area['is_accessible'] ?? false),
                'notes' => filled($area['notes'] ?? null) ? mb_substr(trim((string) $area['notes']), 0, 200) : null, 'sort_order' => $i];

            if ($kind === 'seat_rows') {
                $row['rows'] = (int) ($area['rows'] ?? 0);
                $row['seats_per_row'] = (int) ($area['seats_per_row'] ?? 0);
                if ($row['rows'] < 1 || $row['rows'] > 26 || $row['seats_per_row'] < 1 || $row['seats_per_row'] > 60) {
                    throw new \InvalidArgumentException("'{$label}' needs 1–26 rows of 1–60 seats.");
                }
                $totalSeats += $row['rows'] * $row['seats_per_row'];
            } elseif ($kind === 'table') {
                $row['seats_per_table'] = (int) ($area['seats_per_table'] ?? 0);
                if ($row['seats_per_table'] < 1 || $row['seats_per_table'] > 20) {
                    throw new \InvalidArgumentException("'{$label}' needs 1–20 seats.");
                }
                $totalSeats += $row['seats_per_table'];
            } elseif (in_array($kind, ['general_admission', 'vip'], true) && filled($area['capacity'] ?? null)) {
                $row['capacity'] = max(1, min(65000, (int) $area['capacity']));
            }
            if (in_array($kind, VenueArea::SEATED, true)) {
                $key = mb_strtolower($row['label']);
                if (isset($seatedLabels[$key])) {
                    throw new \InvalidArgumentException("Two seated areas are called '{$label}'. Give each its own name so seats can be told apart.");
                }
                $seatedLabels[$key] = true;
            }
            $clean[] = $row;
        }
        if ($totalSeats > self::MAX_SEATS) {
            throw new \InvalidArgumentException('A plan can have at most ' . self::MAX_SEATS . ' seats.');
        }

        DB::transaction(function () use ($user, $event, $clean, $seatSelection, $notes, $layout) {
            $existing = VenueArea::where('event_id', $event->id)->lockForUpdate()->get()->keyBy('id');
            $taken = $this->takenSeatIds($event, lock: true);
            $keepIds = collect($clean)->pluck('id')->filter()->all();

            foreach ($existing as $id => $area) {
                if (!in_array($id, $keepIds, true)) {
                    if ($area->seats()->whereIn('id', $taken)->exists()) {
                        throw new \InvalidArgumentException("'{$area->label}' has seats that are already sold, so it cannot be removed.");
                    }
                    $area->delete();
                }
            }

            foreach ($clean as $row) {
                $area = $row['id'] && $existing->has($row['id']) ? $existing[$row['id']] : new VenueArea(['event_id' => $event->id]);
                $structureChanged = !$area->exists || $area->kind !== $row['kind'] || $area->rows !== $row['rows']
                    || $area->seats_per_row !== $row['seats_per_row'] || $area->seats_per_table !== $row['seats_per_table'] || $area->label !== $row['label'];
                unset($row['id']);
                $area->fill($row)->save();

                if (!$area->isSeated()) {
                    $area->seats()->delete();
                    continue;
                }
                $soldHere = $area->seats()->whereIn('id', $taken)->exists();
                if ($structureChanged && $soldHere) {
                    throw new \InvalidArgumentException("'{$area->label}' has seats that are already sold, so its name and number of seats cannot change.");
                }
                $structureChanged ? $this->generateSeats($event, $area) : $this->repositionSeats($area);
            }

            DB::table('venue_layouts')->updateOrInsert(['event_id' => $event->id], [
                'seat_selection_enabled' => $seatSelection && collect($clean)->contains(fn ($a) => in_array($a['kind'], VenueArea::SEATED, true) && $a['ticket_tier_id']),
                'width' => $layout['width'], 'height' => $layout['height'],
                'notes' => filled($notes) ? mb_substr(trim((string) $notes), 0, 500) : null,
                'updated_at' => now(), 'created_at' => now(),
            ]);
            AuditLogService::log(action: 'venue_layout_saved', targetEntity: 'events', targetId: (string) $event->id,
                details: "{$event->title}: venue plan saved (" . count($clean) . ' areas).', actor: $user,
                organizerId: (string) $event->organizer_id, eventId: (string) $event->id);
        });
    }

    /** Blocks or unblocks a seat (e.g. for a camera position or a wheelchair space); a sold seat cannot be blocked. */
    public function setBlocked(User $user, string $eventId, string $seatId, bool $blocked): void
    {
        $event = $this->events->findEvent($user, $eventId);
        $seat = VenueSeat::where('event_id', $event->id)->lockForUpdate()->findOrFail($seatId);
        if ($blocked && in_array($seat->id, $this->takenSeatIds($event), true)) {
            throw new \InvalidArgumentException("{$seat->label} is already sold.");
        }
        $seat->forceFill(['is_blocked' => $blocked])->save();
    }

    // ---- Buying -------------------------------------------------------------------------------------------------

    /** Whether buyers choose seats for this event: seat selection is on and some ticket type is seated. */
    public function seatSelectionOpen(Event $event): bool
    {
        return $this->layout($event)['seat_selection_enabled'] && $this->seatedTierIds($event) !== [];
    }

    /** @return list<string> ticket types whose buyers pick seats */
    public function seatedTierIds(Event $event): array
    {
        return VenueArea::where('event_id', $event->id)->whereIn('kind', VenueArea::SEATED)->whereNotNull('ticket_tier_id')
            ->distinct()->pluck('ticket_tier_id')->map(fn ($id) => (string) $id)->values()->all();
    }

    /**
     * Seats for the buyer's map, each with whether it can be chosen.
     *
     * @return Collection<int, array{seat: VenueSeat, area: VenueArea, available: bool}>
     */
    public function seatMap(Event $event): Collection
    {
        $taken = array_flip($this->takenSeatIds($event));
        $areas = $this->areas($event)->keyBy('id');

        return VenueSeat::where('event_id', $event->id)->orderBy('label')->get()->map(fn (VenueSeat $seat) => [
            'seat' => $seat->setRelation('area', $areas[$seat->area_id] ?? null),
            'area' => $areas[$seat->area_id] ?? null,
            'available' => !$seat->is_blocked && !isset($taken[$seat->id]),
        ])->filter(fn ($row) => $row['area'] !== null)->values();
    }

    /**
     * Checks a buyer's seat choice against their ticket quantities without holding anything: every seated ticket
     * type needs exactly one free seat of that type per ticket.
     *
     * @param array<string, int> $quantities ticket type => quantity
     * @param list<string> $seatIds
     * @return array<string, list<string>> seat ids by ticket type
     */
    public function validateChoice(Event $event, array $quantities, array $seatIds, bool $lock = false): array
    {
        $seated = $this->seatedTierIds($event);
        $seatIds = array_values(array_unique(array_map('strval', $seatIds)));
        $query = VenueSeat::with('area')->where('event_id', $event->id)->whereIn('id', $seatIds);
        $seats = ($lock ? $query->lockForUpdate() : $query)->get();
        if ($seats->count() !== count($seatIds)) {
            throw new OrderPlacementException('Some of the chosen seats are not part of this event.');
        }
        $taken = array_flip($this->takenSeatIds($event, lock: $lock));
        $byTier = [];
        foreach ($seats as $seat) {
            if ($seat->is_blocked || isset($taken[$seat->id])) {
                throw new OrderPlacementException("{$seat->spokenName()} has just been taken. Choose another seat.");
            }
            $byTier[(string) $seat->area->ticket_tier_id][] = $seat->id;
        }
        $names = collect(TicketTierCatalog::sellable($event))->pluck('name', 'id');
        foreach ($seated as $tierId) {
            $want = (int) ($quantities[$tierId] ?? 0);
            $have = count($byTier[$tierId] ?? []);
            if ($want !== $have) {
                $name = $names[$tierId] ?? 'these tickets';
                throw new OrderPlacementException("Choose {$want} " . ($want === 1 ? 'seat' : 'seats') . " for {$name} (you chose {$have}).");
            }
        }
        foreach (array_keys($byTier) as $tierId) {
            if (!in_array($tierId, $seated, true) || (int) ($quantities[$tierId] ?? 0) === 0) {
                throw new OrderPlacementException('A chosen seat does not match the tickets in your order.');
            }
        }

        return $byTier;
    }

    /**
     * Gives the chosen seats to a new order. Called inside the order's transaction: the seat rows are locked and
     * checked again, so two buyers can never end up with the same seat.
     *
     * @param array<string, int> $quantities
     * @param list<string> $seatIds
     */
    public function claim(Event $event, Order $order, array $quantities, array $seatIds): void
    {
        if (!$this->seatSelectionOpen($event)) {
            return;
        }
        $this->validateChoice($event, $quantities, $seatIds, lock: true);
        VenueSeat::where('event_id', $event->id)->whereIn('id', $seatIds)->update(['order_id' => $order->id, 'claimed_at' => now()]);
    }

    /** @return Collection<int, VenueSeat> an order's seats (while it holds them) */
    public function seatsFor(Order $order): Collection
    {
        if (in_array($order->status, VenueSeat::RELEASED_ORDER_STATUSES, true) || $order->inventory_released_at) {
            return collect();
        }

        return VenueSeat::with('area')->where('order_id', $order->id)->orderBy('label')->get();
    }

    /**
     * Seats held by live orders. An order that expired, was cancelled or refunded, or whose stock was released, no
     * longer holds its seats.
     *
     * @return list<string>
     */
    public function takenSeatIds(Event $event, bool $lock = false): array
    {
        $query = VenueSeat::query()->where('venue_seats.event_id', $event->id)->whereNotNull('venue_seats.order_id')
            ->join('orders', 'orders.id', '=', 'venue_seats.order_id')
            ->whereNotIn('orders.status', VenueSeat::RELEASED_ORDER_STATUSES)->whereNull('orders.inventory_released_at')
            ->whereNull('orders.deleted_at');

        return ($lock ? $query->lockForUpdate() : $query)->pluck('venue_seats.id')->map(fn ($id) => (string) $id)->all();
    }

    // ---- Seat geometry ------------------------------------------------------------------------------------------

    private function generateSeats(Event $event, VenueArea $area): void
    {
        $area->seats()->delete();
        foreach ($this->positions($area) as $p) {
            VenueSeat::create(['event_id' => $event->id, 'area_id' => $area->id, 'label' => $p['label'], 'row_label' => $p['row'],
                'number' => $p['number'], 'x' => $p['x'], 'y' => $p['y'], 'is_accessible' => $area->is_accessible]);
        }
    }

    private function repositionSeats(VenueArea $area): void
    {
        $positions = collect($this->positions($area))->keyBy('label');
        foreach ($area->seats()->get() as $seat) {
            if ($p = $positions->get($seat->label)) {
                $seat->forceFill(['x' => $p['x'], 'y' => $p['y'], 'is_accessible' => $area->is_accessible])->save();
            }
        }
    }

    /** @return list<array{label: string, row: ?string, number: int, x: int, y: int}> seat centres in plan units */
    private function positions(VenueArea $area): array
    {
        $prefix = mb_substr(preg_replace('/\s+/', ' ', $area->label), 0, 14);
        $out = [];
        if ($area->kind === 'seat_rows') {
            for ($r = 0; $r < $area->rows; $r++) {
                $row = chr(65 + $r);
                for ($n = 1; $n <= $area->seats_per_row; $n++) {
                    $out[] = ['label' => "{$prefix} {$row}{$n}", 'row' => $row, 'number' => $n,
                        'x' => (int) round($area->x + $area->width * ($n - 0.5) / $area->seats_per_row),
                        'y' => (int) round($area->y + $area->height * ($r + 0.5) / $area->rows)];
                }
            }
        } elseif ($area->kind === 'table') {
            $cx = $area->x + $area->width / 2;
            $cy = $area->y + $area->height / 2;
            $rx = $area->width / 2 * 0.8;
            $ry = $area->height / 2 * 0.8;
            for ($n = 1; $n <= $area->seats_per_table; $n++) {
                $angle = 2 * M_PI * ($n - 1) / $area->seats_per_table - M_PI / 2;
                $out[] = ['label' => "{$prefix}-{$n}", 'row' => null, 'number' => $n,
                    'x' => (int) round($cx + $rx * cos($angle)), 'y' => (int) round($cy + $ry * sin($angle))];
            }
        }

        return $out;
    }
}
