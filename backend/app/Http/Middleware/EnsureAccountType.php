<?php

namespace App\Http\Middleware;

use App\Enums\AccountType;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class EnsureAccountType
{
    /**
     * Handle an incoming request.
     * Enforces that the authenticated user possesses the required account type or active context.
     */
    public function handle(Request $request, Closure $next, string ...$roles): Response
    {
        $user = $request->user();

        if (!$user) {
            return response()->json([
                'error' => 'Unauthenticated',
                'message' => 'Authentication required to access this resource.',
            ], 401);
        }

        if ($user->isSuspended()) {
            return response()->json([
                'error' => 'Account Suspended',
                'message' => 'Your account has been suspended. Please contact platform administration.',
            ], 403);
        }

        // System Administrator has global privilege access
        if ($user->hasRole(AccountType::SYSTEM_ADMIN)) {
            return $next($request);
        }

        $activeRole = $user->getActiveRole()->value;

        // Check if active role or any held role matches requested roles
        $authorized = false;
        foreach ($roles as $requiredRole) {
            if ($activeRole === $requiredRole || $user->hasRole($requiredRole)) {
                $authorized = true;
                break;
            }
        }

        if (!$authorized) {
            return response()->json([
                'error' => 'Forbidden',
                'message' => 'You do not have the required role permissions to access this area.',
                'required_roles' => $roles,
                'active_role' => $activeRole,
            ], 403);
        }

        return $next($request);
    }
}
