<?php

namespace App\Services;

use App\Enums\AccountType;
use App\Models\SecurityAuditLog;
use App\Models\User;
use Illuminate\Support\Str;

class ContextSwitchingService
{
    /**
     * Dual-acting users: A single user account can act as an Event Host and Attendee
     * without creating duplicate user accounts.
     *
     * Validates that the user legitimately holds the target role before switching.
     * Prevents any privilege escalation by URL tampering, form tampering, or request header spoofing.
     */
    public static function switchDashboardContext(User $user, string|AccountType $targetRole, ?string $ipAddress = null): array
    {
        $roleEnum = $targetRole instanceof AccountType ? $targetRole : AccountType::tryFrom($targetRole);

        if (!$roleEnum) {
            return [
                'success' => false,
                'message' => 'Invalid dashboard context role specified.',
                'active_role' => $user->getActiveRole()->value,
            ];
        }

        // Account must not be suspended
        if ($user->isSuspended()) {
            return [
                'success' => false,
                'message' => 'Account is suspended. Dashboard switching disabled.',
                'active_role' => $user->getActiveRole()->value,
            ];
        }

        // Check legitimate entitlement:
        // Attendee is universally allowed for active users
        if ($roleEnum !== AccountType::ATTENDEE && !$user->hasRole($roleEnum)) {
            // Log security event for attempted privilege elevation
            SecurityAuditLog::create([
                'id' => (string) Str::uuid(),
                'user_id' => $user->id,
                'actor_role' => $user->getActiveRole()->value,
                'action' => 'unauthorized_context_switch_attempt',
                'target_entity' => 'user_roles',
                'target_id' => $roleEnum->value,
                'ip_address' => $ipAddress ?? '127.0.0.1',
                'user_agent' => 'Dashboard/ContextSwitcher',
                'details' => "User {$user->email} attempted unauthorized switch to {$roleEnum->value} without holding the role.",
                'is_pii_access' => false,
                'created_at' => now(),
            ]);

            return [
                'success' => false,
                'message' => "Forbidden: You are not authorized to switch to the {$roleEnum->label()} dashboard.",
                'active_role' => $user->getActiveRole()->value,
            ];
        }

        // Switch context
        $previousRole = $user->current_context_role;
        $user->current_context_role = $roleEnum->value;
        $user->save();

        // Audit context transition
        SecurityAuditLog::create([
            'id' => (string) Str::uuid(),
            'user_id' => $user->id,
            'actor_role' => $roleEnum->value,
            'action' => 'dashboard_context_switched',
            'target_entity' => 'users',
            'target_id' => (string) $user->id,
            'ip_address' => $ipAddress ?? '127.0.0.1',
            'user_agent' => 'Dashboard/ContextSwitcher',
            'details' => "User {$user->email} switched active dashboard context from {$previousRole} to {$roleEnum->value}.",
            'is_pii_access' => false,
            'created_at' => now(),
        ]);

        return [
            'success' => true,
            'message' => "Switched to {$roleEnum->label()} dashboard context.",
            'active_role' => $roleEnum->value,
            'dashboard_route' => self::getDashboardRouteForRole($roleEnum),
            'redirect_route' => self::getDashboardRouteForRole($roleEnum),
        ];
    }

    /**
     * Resolves the canonical dashboard route for an account type.
     */
    public static function getDashboardRouteForRole(AccountType $role): string
    {
        return match ($role) {
            AccountType::SYSTEM_ADMIN => 'system-admin.dashboard',
            AccountType::ADMINISTRATOR => 'admin.dashboard',
            AccountType::EVENT_HOST => 'host.dashboard',
            AccountType::ATTENDEE => 'attendee.dashboard',
        };
    }
}
