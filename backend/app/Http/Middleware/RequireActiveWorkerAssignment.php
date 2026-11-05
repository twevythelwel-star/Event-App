<?php

namespace App\Http\Middleware;

use App\Exceptions\SecurityException;
use App\Models\Event;
use App\Services\EventWorkerService;
use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Symfony\Component\HttpFoundation\Response;

class RequireActiveWorkerAssignment
{
    /**
     * Ensure the authenticated user may work this event right now: staff need a current, accepted assignment;
     * the event's host, people who run its organizer and system administrators act without one, exactly as
     * EventWorkerService::resolveActiveAssignment decides.
     *
     * The event comes from the route ({event}, already bound to the model) or request input.
     */
    public function handle(Request $request, Closure $next): Response
    {
        $user = Auth::user();
        if (!$user) {
            return response()->json(['error' => 'Unauthenticated'], 401);
        }

        $event = $request->route('event') ?? $request->route('eventId') ?? $request->input('event_id');
        if (!$event instanceof Event) {
            $event = $event ? Event::find($event) : null;
        }
        if (!$event) {
            return response()->json(['error' => 'Event ID required'], 400);
        }

        try {
            $assignment = EventWorkerService::resolveActiveAssignment($user, $event);
        } catch (SecurityException $e) {
            return response()->json(['error' => $e->getMessage()], 403);
        }

        $runsEvent = EventWorkerService::actsWithoutAssignment($user, $event);
        if (!$runsEvent) {
            $assignment->update(['last_active_at' => now()]);
        }

        // Make assignment available to controllers
        $request->attributes->set('worker_assignment', $assignment);
        $request->attributes->set('worker_runs_event', $runsEvent);

        return $next($request);
    }
}
