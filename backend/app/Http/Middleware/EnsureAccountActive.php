<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class EnsureAccountActive
{
    /**
     * Rejects suspended or deactivated accounts.
     */
    public function handle(Request $request, Closure $next): Response
    {
        $user = $request->user();

        if ($user && $user->isSuspended()) {
            return response()->json([
                'error' => 'Account Suspended',
                'message' => 'Your account is currently suspended from platform operations.',
            ], 403);
        }

        return $next($request);
    }
}
