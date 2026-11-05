<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Symfony\Component\HttpFoundation\Response;

class RequireWorkerPermission
{
    /**
     * Ensure the authenticated worker has permission to perform a specific action.
     *
     * Usage: RequireWorkerPermission:scan_qr_tickets
     */
    public function handle(Request $request, Closure $next, string $permission): Response
    {
        $user = Auth::user();
        if (!$user) {
            return response()->json(['error' => 'Unauthenticated'], 401);
        }

        $assignment = $request->attributes->get('worker_assignment');
        if (!$assignment) {
            return response()->json(['error' => 'No active worker assignment'], 403);
        }

        // The event's host, its organizer's managers and system administrators are not limited by a staff role
        // (see EventWorkerService::actsWithoutAssignment).
        if (!$request->attributes->get('worker_runs_event') && !$assignment->canPerform($permission)) {
            return response()->json([
                'error' => 'Permission denied',
                'required_permission' => $permission,
                'worker_role' => $assignment->worker_role->value,
            ], 403);
        }

        return $next($request);
    }
}
