<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Symfony\Component\HttpFoundation\Response;

class ProhibitedActionGuard
{
    /**
     * Ensure the authenticated worker is not performing a prohibited action.
     *
     * Usage: ProhibitedActionGuard:change_ticket_prices
     */
    public function handle(Request $request, Closure $next, string $action): Response
    {
        $user = Auth::user();
        if (!$user) {
            return response()->json(['error' => 'Unauthenticated'], 401);
        }

        $assignment = $request->attributes->get('worker_assignment');
        if (!$assignment) {
            return response()->json(['error' => 'No active worker assignment'], 403);
        }

        if ($assignment->isProhibited($action)) {
            \App\Services\AuditLogService::log(
                action: 'prohibited_action_attempt',
                targetEntity: 'event_worker_assignments',
                targetId: (string) $assignment->id,
                details: "Worker attempted prohibited action: {$action}",
                actor: $user
            );

            return response()->json([
                'error' => 'Action prohibited for this role',
                'prohibited_action' => $action,
                'worker_role' => $assignment->worker_role->value,
            ], 403);
        }

        return $next($request);
    }
}
