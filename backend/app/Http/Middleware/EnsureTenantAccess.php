<?php

namespace App\Http\Middleware;

use App\Enums\TenantRole;
use App\Models\Organizer;
use App\Models\OrganizerUser;
use App\Services\TenantContext;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class EnsureTenantAccess
{
    /**
     * Handle an incoming request and enforce tenant isolation.
     *
     * @param  \Closure(\Illuminate\Http\Request): (\Symfony\Component\HttpFoundation\Response)  $next
     * @param  string|null  $requiredRole Optional minimum role required for this endpoint
     */
    public function handle(Request $request, Closure $next, ?string $requiredRole = null): Response
    {
        // 1. Resolve Tenant from Header, Route Parameter, or Subdomain
        $tenantIdentifier = $request->header('X-Organizer-ID') 
            ?? $request->route('organizer') 
            ?? $this->resolveFromSubdomain($request);

        if (!$tenantIdentifier) {
            return response()->json([
                'error' => 'tenant_unresolved',
                'message' => 'Missing required X-Organizer-ID header or tenant route context.',
            ], 400);
        }

        // 2. Fetch Organizer
        $organizer = Organizer::where('id', $tenantIdentifier)
            ->orWhere('slug', $tenantIdentifier)
            ->first();

        if (!$organizer) {
            return response()->json([
                'error' => 'tenant_not_found',
                'message' => 'The specified organizer tenant does not exist or has been removed.',
            ], 404);
        }

        if ($organizer->status === 'suspended') {
            return response()->json([
                'error' => 'tenant_suspended',
                'message' => 'This organizer account is currently suspended. Contact platform administrator.',
            ], 403);
        }

        $user = $request->user();

        // 3. Authenticate Tenant Membership & Role (Unless public endpoint like customer event page)
        if ($user) {
            // Check if user is a global platform admin
            $isPlatformAdmin = $user->is_platform_admin ?? false;

            // Check organizer membership
            $membership = OrganizerUser::where('organizer_id', $organizer->id)
                ->where('user_id', $user->id)
                ->where('is_active', true)
                ->first();

            if (!$membership && !$isPlatformAdmin) {
                // Strict isolation boundary: prevent IDOR cross-tenant access
                return response()->json([
                    'error' => 'tenant_forbidden',
                    'message' => 'Tenant Isolation Boundary Violation: Cross-organizer access blocked.',
                    'attempted_tenant' => $organizer->id,
                    'authenticated_user_id' => $user->id,
                ], 403);
            }

            // Verify role requirements if specified
            if ($requiredRole && !$isPlatformAdmin) {
                $userRole = $membership?->role;
                if (!$this->satisfiesRoleRequirement($userRole, $requiredRole)) {
                    return response()->json([
                        'error' => 'insufficient_tenant_role',
                        'message' => "This action requires [{$requiredRole}] authority on this organizer.",
                        'current_role' => $userRole?->value,
                    ], 403);
                }
            }

            // Update user's last active timestamp on this organizer
            $membership?->update(['last_active_at' => now()]);
            $request->attributes->set('tenant_role', $isPlatformAdmin ? TenantRole::PLATFORM_ADMIN : $membership->role);
        }

        // 4. Bind Tenant to Global Request Context
        TenantContext::set($organizer);

        try {
            $response = $next($request);
            // Append tenant verification header in non-production for observability
            $response->headers->set('X-Active-Tenant', $organizer->slug);
            return $response;
        } finally {
            // Guarantee context cleanup
            TenantContext::clear();
        }
    }

    /**
     * Resolve organizer slug from custom subdomain or host.
     */
    protected function resolveFromSubdomain(Request $request): ?string
    {
        $host = $request->getHost();
        $parts = explode('.', $host);

        // e.g. "kingstonyouth.events.app" -> "kingstonyouth"
        if (count($parts) >= 3 && !in_array($parts[0], ['www', 'api', 'app', 'localhost'])) {
            return $parts[0];
        }

        return null;
    }

    /**
     * Check if user's role satisfies minimum role tier.
     */
    protected function satisfiesRoleRequirement(?TenantRole $userRole, string $requiredRole): bool
    {
        if (!$userRole) return false;

        $hierarchy = [
            TenantRole::FOOD_COLLECTOR->value => 1,
            TenantRole::PAYMENT_VERIFIER->value => 2,
            TenantRole::EVENT_OWNER->value => 3,
            TenantRole::PLATFORM_ADMIN->value => 4,
        ];

        $userLevel = $hierarchy[$userRole->value] ?? 0;
        $requiredLevel = $hierarchy[$requiredRole] ?? 99;

        return $userLevel >= $requiredLevel;
    }
}
