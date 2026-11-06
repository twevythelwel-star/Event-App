<?php

namespace App\Http\Controllers;

use App\Models\VenueArea;
use App\Models\VenueSeat;
use App\Services\EventManagementService;
use App\Services\Events\SeatingService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

/** The host's venue and seating designer. Authorization and every rule live in SeatingService. */
class VenueDesignerController extends Controller
{
    public function __construct(
        private readonly EventManagementService $events,
        private readonly SeatingService $seating,
    ) {}

    public function show(Request $request, string $id): View
    {
        $event = $this->events->findEvent($request->user(), $id);
        $taken = array_flip($this->seating->takenSeatIds($event));
        $seats = VenueSeat::where('event_id', $event->id)->orderBy('label')->get();

        return view('events.venue-designer', [
            'event' => $event,
            'layout' => $this->seating->layout($event),
            'areas' => $this->seating->areas($event),
            'tiers' => collect(is_array($event->ticket_tiers) ? $event->ticket_tiers : [])->map(fn ($t) => ['id' => (string) ($t['id'] ?? ''), 'name' => (string) ($t['name'] ?? 'Ticket')])->filter(fn ($t) => $t['id'] !== '')->values(),
            'seatCounts' => $seats->groupBy('area_id')->map(fn ($group) => ['total' => $group->count(), 'sold' => $group->filter(fn ($s) => isset($taken[$s->id]))->count(), 'blocked' => $group->where('is_blocked', true)->count()]),
        ]);
    }

    public function save(Request $request, string $id): RedirectResponse
    {
        $data = $request->validate([
            'areas' => ['nullable', 'array', 'max:' . SeatingService::MAX_AREAS],
            'areas.*.id' => ['nullable', 'string', 'max:64'],
            'areas.*.kind' => ['required', Rule::in(array_keys(VenueArea::KINDS))],
            'areas.*.label' => ['required', 'string', 'max:60'],
            'areas.*.x' => ['required', 'integer', 'min:0'],
            'areas.*.y' => ['required', 'integer', 'min:0'],
            'areas.*.width' => ['required', 'integer', 'min:20'],
            'areas.*.height' => ['required', 'integer', 'min:20'],
            'areas.*.ticket_tier_id' => ['nullable', 'string', 'max:64'],
            'areas.*.capacity' => ['nullable', 'integer', 'min:1', 'max:65000'],
            'areas.*.rows' => ['nullable', 'integer', 'min:1', 'max:26'],
            'areas.*.seats_per_row' => ['nullable', 'integer', 'min:1', 'max:60'],
            'areas.*.seats_per_table' => ['nullable', 'integer', 'min:1', 'max:20'],
            'areas.*.is_accessible' => ['nullable', 'boolean'],
            'areas.*.notes' => ['nullable', 'string', 'max:200'],
            'notes' => ['nullable', 'string', 'max:500'],
        ]);

        try {
            $this->seating->saveLayout($request->user(), $id, array_values($data['areas'] ?? []), $request->boolean('seat_selection_enabled'), $data['notes'] ?? null);
        } catch (\InvalidArgumentException $e) {
            return back()->withInput()->withErrors(['venue' => $e->getMessage()]);
        }

        return redirect()->route('events.venue-designer', $id)->with('status', 'Venue plan saved.');
    }
}
