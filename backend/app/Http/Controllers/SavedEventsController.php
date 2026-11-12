<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Models\Event;
use App\Models\SavedEvent;
use App\Support\EventListing;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

/**
 * Saved events: attendees bookmark published events from the event page and revisit them from
 * the Saved tab of the customer bottom navigation. Bookmarks are private to the account.
 */
class SavedEventsController extends Controller
{
    public function index(Request $request): View
    {
        $savedIds = SavedEvent::where('user_id', $request->user()->id)->pluck('event_id');

        // Same visibility rules as public discovery: published, public, not ended, soonest first.
        $events = Event::allTenants()
            ->whereIn('id', $savedIds)
            ->where('status', 'published')
            ->where('visibility', 'public')
            ->where(fn ($q) => $q->whereNull('event_date_iso')->orWhereDate('event_date_iso', '>=', now()->toDateString()))
            ->orderByRaw('event_date_iso IS NULL')->orderBy('event_date_iso')->orderBy('start_time')
            ->get()
            ->reject(fn (Event $event) => EventListing::hasEnded($event))
            ->values();

        return view('saved.index', [
            'events' => $events,
            'savedIds' => $savedIds,
        ]);
    }

    public function toggle(Request $request, string $eventId): RedirectResponse
    {
        $user = $request->user();
        $event = Event::allTenants()->whereKey($eventId)->where('status', 'published')->firstOrFail();

        $saved = SavedEvent::where('user_id', $user->id)->where('event_id', $event->id)->first();
        if ($saved) {
            $saved->delete();
            $message = "'{$event->title}' was removed from your saved events.";
        } else {
            SavedEvent::firstOrCreate(['user_id' => $user->id, 'event_id' => $event->id]);
            $message = "'{$event->title}' was saved. Find it under Saved in the bottom navigation.";
        }

        if ($request->wantsJson()) {
            return response()->json(['success' => true, 'saved' => $saved === null, 'message' => $message]);
        }

        return back()->with('status', $message);
    }
}
