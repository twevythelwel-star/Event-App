<?php

declare(strict_types=1);

namespace App\Services;

use App\Enums\AccountType;
use App\Exceptions\SecurityException;
use App\Models\SecurityAuditLog;
use App\Models\User;
use App\Models\UserRole;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class AuthenticationService
{
    /**
     * Register a new user account with default Attendee role and transactional profile persistence.
     *
     * @param array<string, mixed> $data
     * @return array{success: bool, user: User, message: string, redirect_route: string}
     * @throws \InvalidArgumentException|SecurityException
     */
    public static function register(array $data): array
    {
        // 1. Enforce strict anti-escalation security check: browser can NEVER request system_admin or admin
        SystemAdminProvisioningService::assertPublicRegistrationSafe($data);

        // 2. Validate required registration fields
        $firstName = trim((string) ($data['first_name'] ?? ''));
        $lastName = trim((string) ($data['last_name'] ?? ''));
        $email = strtolower(trim((string) ($data['email'] ?? '')));
        $password = (string) ($data['password'] ?? '');
        $passwordConfirm = (string) ($data['password_confirmation'] ?? '');
        $agreeTerms = !empty($data['agree_terms']);

        if (empty($firstName) || empty($lastName)) {
            throw new \InvalidArgumentException('First name and last name are required.');
        }

        if (empty($email) || !filter_var($email, FILTER_VALIDATE_EMAIL)) {
            throw new \InvalidArgumentException('A valid email address is required.');
        }

        if (strlen($password) < 8) {
            throw new \InvalidArgumentException('Password must be at least 8 characters in length.');
        }

        if ($password !== $passwordConfirm) {
            throw new \InvalidArgumentException('Password confirmation does not match.');
        }

        if (!$agreeTerms) {
            throw new \InvalidArgumentException('You must agree to the platform terms and privacy policy.');
        }

        // 3. Database uniqueness check
        if (User::where('email', $email)->exists()) {
            throw new \InvalidArgumentException('An account with this email address already exists.');
        }

        // 4. Atomic multi-record registration transaction
        $user = DB::transaction(function () use ($firstName, $lastName, $email, $password, $data) {
            $hashedPassword = password_hash($password, PASSWORD_DEFAULT);

            $user = User::create([
                'name' => "{$firstName} {$lastName}",
                'first_name' => $firstName,
                'last_name' => $lastName,
                'email' => $email,
                'phone' => !empty($data['phone']) ? trim((string) $data['phone']) : null,
                'password' => $hashedPassword,
                'status' => 'active',
                'preferred_display_currency' => in_array($data['preferred_display_currency'] ?? 'USD', ['JMD', 'USD'], true)
                    ? ($data['preferred_display_currency'] ?? 'USD')
                    : 'USD',
                'preferred_language' => 'en',
                'current_context_role' => AccountType::ATTENDEE->value,
                'is_system_admin' => false,
                'agreed_to_terms_at' => now(),
            ]);

            // Assign default primary account role: Attendee
            UserRole::create([
                'user_id' => $user->id,
                'role' => AccountType::ATTENDEE->value,
                'is_active' => true,
                'assigned_at' => now(),
            ]);

            return $user;
        });

        // 5. Immutable security audit log
        $auditId = (string) Str::uuid();
        SecurityAuditLog::create([
            'id' => $auditId,
            'user_id' => $user->id,
            'actor_role' => AccountType::ATTENDEE->value,
            'action' => 'user_registered',
            'target_entity' => 'users',
            'target_id' => (string) $user->id,
            'ip_address' => request()->ip() ?? '127.0.0.1',
            'user_agent' => request()->userAgent() ?? 'WebBrowser/1.0',
            'details' => "User {$user->email} registered with default Attendee role.",
            'is_pii_access' => false,
            'created_at' => now(),
        ]);

        return [
            'success' => true,
            'user' => $user,
            'message' => 'Registration successful! Welcome to EventBite.',
            'redirect_route' => 'attendee.dashboard',
        ];
    }

    /**
     * Authenticate user credentials and resolve their authorized dashboard route.
     *
     * @return array{success: bool, user?: User, error?: string, redirect_route?: string}
     */
    public static function login(string $email, string $password, bool $remember = false): array
    {
        $normalizedEmail = strtolower(trim($email));

        $user = User::where('email', $normalizedEmail)->first();

        // Generic error on missing user or incorrect password to prevent user enumeration
        if (!$user || !password_verify($password, $user->password)) {
            return [
                'success' => false,
                'error' => 'Invalid email address or password.',
            ];
        }

        // Account status check
        if ($user->isSuspended()) {
            return [
                'success' => false,
                'error' => 'Your account is suspended. Please contact platform support.',
            ];
        }

        // Resolve dashboard route based on user's authorized active context
        $activeRole = $user->getActiveRole();
        $redirectRoute = ContextSwitchingService::getDashboardRouteForRole($activeRole);

        // Security Audit Log for successful authentication
        SecurityAuditLog::create([
            'id' => (string) Str::uuid(),
            'user_id' => $user->id,
            'actor_role' => $activeRole->value,
            'action' => 'user_logged_in',
            'target_entity' => 'users',
            'target_id' => (string) $user->id,
            'ip_address' => request()->ip() ?? '127.0.0.1',
            'user_agent' => request()->userAgent() ?? 'WebBrowser/1.0',
            'details' => "User {$user->email} authenticated successfully into {$activeRole->value} dashboard.",
            'is_pii_access' => false,
            'created_at' => now(),
        ]);

        return [
            'success' => true,
            'user' => $user,
            'redirect_route' => $redirectRoute,
        ];
    }

    /**
     * Generate an expiring, cryptographic password-reset token.
     */
    public static function createPasswordResetToken(string $email): string
    {
        $normalizedEmail = strtolower(trim($email));

        // Generate unguessable 64-char token
        $plainToken = bin2hex(random_bytes(32));
        $hashedToken = hash('sha256', $plainToken);

        // Store hashed token in database
        DB::table('password_reset_tokens')->updateOrInsert(
            ['email' => $normalizedEmail],
            [
                'token' => $hashedToken,
                'created_at' => now(),
            ]
        );

        return $plainToken;
    }

    /**
     * Reset user password using token hash with expiry check (60 minutes).
     *
     * @return array{success: bool, error?: string, message?: string}
     */
    public static function resetPassword(string $email, string $plainToken, string $newPassword): array
    {
        $normalizedEmail = strtolower(trim($email));

        if (strlen($newPassword) < 8) {
            return [
                'success' => false,
                'error' => 'Password must be at least 8 characters in length.',
            ];
        }

        $record = DB::table('password_reset_tokens')->where('email', $normalizedEmail)->first();
        if (!$record) {
            return [
                'success' => false,
                'error' => 'Invalid or expired password reset link.',
            ];
        }

        // Check 60-minute expiry window
        $createdAt = $record->created_at instanceof \DateTimeInterface
            ? $record->created_at
            : new \DateTime((string) $record->created_at);
        $diffMinutes = (time() - $createdAt->getTimestamp()) / 60;
        if ($diffMinutes > 60) {
            DB::table('password_reset_tokens')->where('email', $normalizedEmail)->delete();
            return [
                'success' => false,
                'error' => 'This password reset link has expired. Please request a new one.',
            ];
        }

        // Verify token hash
        $expectedHash = hash('sha256', $plainToken);
        if (!hash_equals($record->token, $expectedHash)) {
            return [
                'success' => false,
                'error' => 'Invalid password reset token.',
            ];
        }

        // Update user password and clear token
        $user = User::where('email', $normalizedEmail)->first();
        if (!$user) {
            return [
                'success' => false,
                'error' => 'User account not found.',
            ];
        }

        $user->password = password_hash($newPassword, PASSWORD_DEFAULT);
        $user->save();

        DB::table('password_reset_tokens')->where('email', $normalizedEmail)->delete();

        // Audit password reset event
        SecurityAuditLog::create([
            'id' => (string) Str::uuid(),
            'user_id' => $user->id,
            'actor_role' => $user->getActiveRole()->value,
            'action' => 'password_reset_completed',
            'target_entity' => 'users',
            'target_id' => (string) $user->id,
            'ip_address' => request()->ip() ?? '127.0.0.1',
            'user_agent' => request()->userAgent() ?? 'WebBrowser/1.0',
            'details' => "Password reset completed successfully for {$user->email}.",
            'is_pii_access' => false,
            'created_at' => now(),
        ]);

        return [
            'success' => true,
            'message' => 'Password has been reset successfully. You may now sign in.',
        ];
    }
}
