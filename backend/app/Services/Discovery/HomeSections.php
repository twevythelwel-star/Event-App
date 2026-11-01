<?php

declare(strict_types=1);

namespace App\Services\Discovery;

use App\Models\Event;
use App\Models\TargetMarket;
use App\Support\EventListing;
use Illuminate\Support\Collection;

/**
 * Homepage sections, built only from published, upcoming events in the database. A section with nothing in
 * it is left out rather than filled with placeholders. Each event's traditions are the ones its host chose
 * (config/traditions.php), or a suggestion from its own text until the host chooses; no tradition is the default.
 */
final class HomeSections
{
    /** Words that mark a seasonal or cultural celebration. */
    private const CELEBRATION_WORDS = ['independence', 'heritage', 'holiday', 'lunar new year', 'carnival', 'juneteenth',
        'thanksgiving', 'christmas', 'kwanzaa', 'diwali', 'emancipation', 'black history', 'hispanic heritage', 'new year'];

    /** Words that mark live music, across genres. */
    private const MUSIC_PATTERN = '/\b(live music|concert|band|dj|jazz|gospel|reggae|dancehall|salsa|samba|soca|kompa|afrobeats|hip[- ]hop|r&b|blues|mariachi|bachata|k-pop|bhangra)\b/u';

    /** Upcoming celebrations are featured only when they fall within this many days. */
    private const SEASONAL_WINDOW_DAYS = 120;

    /** @return Collection<int, Event> published events that have not yet happened, soonest first */
    public static function upcoming(): Collection
    {
        return Event::allTenants()->with('menuItems')->where('status', 'published')
            ->orderByRaw('event_date_iso IS NULL')->orderBy('event_date_iso')->orderBy('start_time')->get()
            ->reject(fn (Event $e) => EventListing::hasEnded($e))->values();
    }

    /** @return list<string> the labels of the traditions an event celebrates */
    public static function traditions(Event $event): array
    {
        return $event->traditionLabels();
    }

    /**
     * @return array{
     *     featured: Collection, near: Collection, foodFestivals: Collection, foodTraditions: Collection,
     *     music: Collection, culture: Collection, family: Collection, seasonal: Collection,
     *     markets: Collection, traditions: array<string, int>, market: ?TargetMarket
     * }
     */
    public static function build(?TargetMarket $market = null): array
    {
        $events = self::upcoming();
        $featuredMarkets = TargetMarket::where('is_enabled', true)->where('is_featured', true)->get()
            ->map(fn (TargetMarket $m) => self::marketKey($m->state_code, $m->city))->all();

        $category = fn (Event $e) => strtolower((string) $e->category);
        $isFood = fn (Event $e) => str_contains($category($e), 'food');
        $isFestival = fn (Event $e) => $isFood($e) && (str_contains($category($e), 'festival') || str_contains(strtolower((string) $e->title), 'festival'));

        $featured = $events->filter(fn (Event $e) => in_array(self::marketKey($e->state_code, $e->city), $featuredMarkets, true))->take(4);
        if ($featured->count() < 4) {
            $featured = $featured->concat($events->diff($featured)->take(4 - $featured->count()));
        }

        $foodFestivals = self::diverse($events->filter($isFestival), 4);
        $foodTraditions = self::diverse($events->filter(fn (Event $e) => $isFood($e) && !$isFestival($e)), 4);

        $near = collect();
        if ($market) {
            $near = $events->filter(fn (Event $e) => $e->state_code === $market->state_code)
                ->sortBy(fn (Event $e) => strcasecmp((string) $e->city, $market->city) === 0 ? 0 : 1)->take(4)->values();
        }

        $seasonalCutoff = now()->addDays(self::SEASONAL_WINDOW_DAYS)->toDateString();

        $counts = $events->groupBy(fn (Event $e) => self::marketKey($e->state_code, $e->city))->map->count();
        $markets = TargetMarket::where('is_enabled', true)->orderByDesc('is_featured')->orderBy('discovery_priority')->orderBy('city')->get()
            ->map(function (TargetMarket $m) use ($counts) {
                $m->setAttribute('upcoming_count', (int) ($counts[self::marketKey($m->state_code, $m->city)] ?? 0));

                return $m;
            })
            ->filter(fn (TargetMarket $m) => $m->upcoming_count > 0)->take(12)->values();

        $traditionCounts = [];
        foreach (array_column((array) config('traditions', []), 'label') as $label) {
            $count = $events->filter(fn (Event $e) => in_array($label, self::traditions($e), true))->count();
            if ($count > 0) {
                $traditionCounts[$label] = $count;
            }
        }

        return [
            'featured' => $featured->values(),
            'near' => $near,
            'foodFestivals' => $foodFestivals,
            'foodTraditions' => $foodTraditions,
            // Live music of every kind; no single genre gets a section of its own.
            'music' => self::diverse($events->filter(fn (Event $e) => str_contains($category($e), 'music') || preg_match(self::MUSIC_PATTERN, self::text($e)) === 1), 4),
            'culture' => $events->filter(fn (Event $e) => preg_match('/cultur|communit|heritage|celebrat/', $category($e) . ' ' . strtolower((string) $e->title)) === 1)->take(4)->values(),
            'family' => $events->filter(fn (Event $e) => str_contains($category($e), 'family') || self::hasFamilyTickets($e))->take(4)->values(),
            'seasonal' => $events->filter(fn (Event $e) => ($e->event_date_iso ?? '9999') <= $seasonalCutoff
                && collect(self::CELEBRATION_WORDS)->contains(fn ($w) => str_contains(strtolower($e->title . ' ' . $e->tagline), $w)))->take(4)->values(),
            'markets' => $markets,
            'traditions' => $traditionCounts,
            'market' => $market,
        ];
    }

    public static function marketKey(?string $state, ?string $city): string
    {
        return strtoupper((string) $state) . ':' . strtolower(trim((string) $city));
    }

    /** Lower-case text the event says about itself: title, tagline, description, category and active menu. */
    public static function text(Event $event): string
    {
        $menu = $event->menuItems->where('is_active', true)->map(fn ($item) => $item->name . ' ' . $item->description)->implode(' ');

        return strtolower(implode(' ', [$event->title, $event->tagline, $event->description, $event->category, $menu]));
    }

    private static function hasFamilyTickets(Event $event): bool
    {
        return collect(is_array($event->ticket_tiers) ? $event->ticket_tiers : [])
            ->contains(fn ($tier) => is_array($tier) && preg_match('/family|child|kids/i', (string) ($tier['name'] ?? '')) === 1);
    }

    /**
     * Up to $limit events, soonest first, but taking one per tradition before repeating a tradition, so a
     * section shows a range of cuisines rather than several events of the same kind.
     *
     * @param Collection<int, Event> $events
     * @return Collection<int, Event>
     */
    private static function diverse(Collection $events, int $limit): Collection
    {
        $picked = collect();
        $seen = [];
        foreach ($events as $event) {
            $traditions = self::traditions($event);
            if ($traditions === [] || array_diff($traditions, $seen) !== []) {
                $picked->push($event);
                $seen = array_merge($seen, $traditions);
            }
            if ($picked->count() >= $limit) {
                return $picked->values();
            }
        }

        return $picked->concat($events->diff($picked))->take($limit)->values();
    }
}
