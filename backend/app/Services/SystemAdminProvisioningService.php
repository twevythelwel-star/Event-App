<?php

namespace App\Services;

use App\Enums\AccountType;
use App\Exceptions\SecurityException;
use App\Models\SecurityAuditLog;
use App\Models\User;
use App\Models\UserRole;
use Illuminate\Support\Str;

class SystemAdminProvisioningService
{
    /**
     * Provisions the initial root System Administrator.
     * This is strictly a CLI / installation command operation, NEVER exposed via public HTTP registration.
     */
    public static function provisionInitialSystemAdmin(array $data): array
    {
        // 1. Enforce required fields
        if (empty($data['email']) || empty($data['name']) || empty($data['password'])) {
            throw new \InvalidArgumentException('Name, email, and password are required to provision System Administrator.');
        }

        // 2. Enforce strong password complexity
        if (strlen($data['password']) < 12) {
            throw new \InvalidArgumentException('System Administrator password must be at least 12 characters.');
        }

        // 3. Create or elevate user
        $user = User::firstOrNew(['email' => strtolower(trim($data['email']))]);
        $user->name = trim($data['name']);
        $user->phone = $data['phone'] ?? '+1 (305) 555-0100';
        $user->password = password_hash($data['password'], PASSWORD_DEFAULT);
        $user->status = 'active';
        $user->is_system_admin = true;
        $user->current_context_role = AccountType::SYSTEM_ADMIN->value;
        $user->email_verified_at = now();
        $user->phone_verified_at = now();
        $user->save();

        // 4. Assign explicit user role
        UserRole::firstOrCreate(
            ['user_id' => $user->id, 'role' => AccountType::SYSTEM_ADMIN->value],
            ['is_active' => true, 'assigned_at' => now()]
        );

        // 5. Generate immutable security audit log
        $auditId = (string) Str::uuid();
        SecurityAuditLog::create([
            'id' => $auditId,
            'user_id' => $user->id,
            'actor_role' => AccountType::SYSTEM_ADMIN->value,
            'action' => 'system_admin_provisioned',
            'target_entity' => 'users',
            'target_id' => (string) $user->id,
            'ip_address' => '127.0.0.1',
            'user_agent' => 'CLI/Deployment_Provisioner',
            'details' => "System Administrator {$user->email} provisioned through verified deployment process.",
            'is_pii_access' => false,
            'created_at' => now(),
        ]);

        return [
            'success' => true,
            'user_id' => $user->id,
            'email' => $user->email,
            'audit_id' => $auditId,
        ];
    }

    /**
     * Guard against public registration privilege escalation.
     * Throws exception if any incoming registration attempts to claim system_admin or admin.
     */
    public static function assertPublicRegistrationSafe(array $payload): void
    {
        $requestedRole = $payload['role'] ?? null;
        if ($requestedRole === AccountType::SYSTEM_ADMIN->value || !empty($payload['is_system_admin'])) {
            throw new SecurityException('Security Violation: System Administrator privileges cannot be registered publicly.');
        }

        if ($requestedRole === AccountType::ADMINISTRATOR->value || $requestedRole === 'administrator') {
            throw new SecurityException('Security Violation: Delegated Administrator privileges require explicit internal assignment.');
        }
    }
}
