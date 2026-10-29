<?php

namespace App\Models;

use App\Enums\AccountType;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;

class User extends Authenticatable
{
    use HasFactory, Notifiable, SoftDeletes;

    protected $fillable = [
        'name',
        'first_name',
        'last_name',
        'email',
        'phone',
        'avatar_url',
        'bio',
        'marketing_preferences',
        'password',
        'status',
        'preferred_display_currency',
        'preferred_language',
        'current_context_role',
        'is_system_admin',
        'two_factor_secret',
        'two_factor_confirmed_at',
        'email_verified_at',
        'phone_verified_at',
        'agreed_to_terms_at',
    ];

    protected $hidden = [
        'password',
        'remember_token',
        'two_factor_secret',
    ];

    protected $casts = [
        'email_verified_at' => 'datetime',
        'phone_verified_at' => 'datetime',
        'two_factor_confirmed_at' => 'datetime',
        'agreed_to_terms_at' => 'datetime',
        'is_system_admin' => 'boolean',
        'marketing_preferences' => 'array',
    ];

    /**
     * Normalize email on setting.
     */
    public function setEmailAttribute(string $value): void
    {
        $this->attributes['email'] = strtolower(trim($value));
    }

    /**
     * Compute full name from first and last name if populated.
     */
    public function getNameAttribute(?string $value): string
    {
        $split = trim(($this->first_name ?? '') . ' ' . ($this->last_name ?? ''));
        return $split ?: ($value ?? '');
    }

    /**
     * Explicit roles assigned to this user.
     */
    public function userRoles(): HasMany
    {
        return $this->hasMany(UserRole::class, 'user_id');
    }

    /**
     * Admin-specific scoped permissions if this user is a Delegated Administrator.
     */
    public function adminPermissions(): HasOne
    {
        return $this->hasOne(AdminPermission::class, 'user_id');
    }

    /**
     * Account-specific settings.
     */
    public function accountSettings(): HasMany
    {
        return $this->hasMany(AccountSetting::class, 'user_id');
    }

    /**
     * Audit logs triggered by or pertaining to this user.
     */
    public function auditLogs(): HasMany
    {
        return $this->hasMany(SecurityAuditLog::class, 'user_id');
    }

    /**
     * Orders placed by this attendee.
     */
    public function orders(): HasMany
    {
        return $this->hasMany(Order::class, 'user_id');
    }

    /**
     * Host applications submitted by this user.
     */
    public function hostApplications(): HasMany
    {
        return $this->hasMany(HostApplication::class, 'user_id');
    }

    /**
     * Organizers (tenants) where this user has membership.
     */
    public function organizerMemberships(): HasMany
    {
        return $this->hasMany(OrganizerUser::class, 'user_id');
    }

    /**
     * Event-specific staff assignments (payment clerk, ticket checker, etc.).
     */
    public function workerAssignments(): HasMany
    {
        return $this->hasMany(EventWorkerAssignment::class, 'user_id');
    }

    /**
     * Check if the user holds an explicit active role.
     */
    public function hasRole(AccountType|string $role): bool
    {
        $roleValue = $role instanceof AccountType ? $role->value : $role;

        // If checking system_admin, verify is_system_admin flag OR explicit active role
        if ($roleValue === AccountType::SYSTEM_ADMIN->value) {
            if ($this->is_system_admin) {
                return true;
            }
        }

        if (isset($this->role) && $this->role === $roleValue) {
            return true;
        }

        // current_context_role is only the dashboard the user last chose; it never grants a role by itself,
        // otherwise a revoked user_roles assignment would keep working until the user switched context

        if ($this->relationLoaded('userRoles')) {
            return $this->userRoles
                ->where('is_active', true)
                ->contains('role', $roleValue);
        }

        return $this->userRoles()
            ->where('role', $roleValue)
            ->where('is_active', true)
            ->exists();
    }

    /**
     * Get all active roles assigned to this user.
     * Every user by default can act as an Attendee if active.
     */
    public function getAuthorizedRoles(): array
    {
        $roles = $this->userRoles()
            ->where('is_active', true)
            ->pluck('role')
            ->toArray();

        if ($this->is_system_admin && !in_array(AccountType::SYSTEM_ADMIN->value, $roles)) {
            $roles[] = AccountType::SYSTEM_ADMIN->value;
        }

        // Standard users always have attendee access if not explicitly assigned
        if (!in_array(AccountType::ATTENDEE->value, $roles)) {
            $roles[] = AccountType::ATTENDEE->value;
        }

        return array_values(array_unique($roles));
    }

    /**
     * Returns the active context role for the current dashboard session.
     */
    public function getActiveRole(): AccountType
    {
        $current = $this->current_context_role ?? AccountType::ATTENDEE->value;

        // Validate that user is still authorized for this context
        if (!$this->hasRole($current) && $current !== AccountType::ATTENDEE->value) {
            $current = AccountType::ATTENDEE->value;
        }

        return AccountType::tryFrom($current) ?? AccountType::ATTENDEE;
    }

    /**
     * Securely switch the active dashboard context role.
     * Invariant: A user can NEVER switch to a role they do not legitimately hold.
     */
    public function switchContextRole(AccountType|string $newRole): bool
    {
        $targetRole = $newRole instanceof AccountType ? $newRole : AccountType::tryFrom($newRole);

        if (!$targetRole) {
            return false;
        }

        // Must hold the role legitimately
        if (!$this->hasRole($targetRole) && $targetRole !== AccountType::ATTENDEE) {
            return false;
        }

        $this->current_context_role = $targetRole->value;
        $this->save();

        return true;
    }

    /**
     * Whether user can elevate privileges or assign roles to another user.
     * Universal Rules:
     * 1. Nobody can elevate their own privileges.
     * 2. Ordinary Administrators CANNOT create or promote System Administrators.
     * 3. Only an active System Administrator can provision or assign System Administrator privileges.
     */
    public function canAssignRole(User $targetUser, AccountType|string $roleToAssign): bool
    {
        $roleValue = $roleToAssign instanceof AccountType ? $roleToAssign->value : $roleToAssign;

        // Invariant 1: Prevent self-elevation
        if ($this->id === $targetUser->id) {
            return false;
        }

        // Invariant 2: Promoting to System Administrator strictly requires System Administrator caller
        if ($roleValue === AccountType::SYSTEM_ADMIN->value) {
            return $this->hasRole(AccountType::SYSTEM_ADMIN);
        }

        // Invariant 3: System Administrator can assign any role
        if ($this->hasRole(AccountType::SYSTEM_ADMIN)) {
            return true;
        }

        // Invariant 4: Delegated Administrator can only assign Event Host or Attendee if explicitly permitted
        if ($this->hasRole(AccountType::ADMINISTRATOR)) {
            if ($roleValue === AccountType::ADMINISTRATOR->value) {
                return false; // Ordinary admins cannot create other admins without superadmin
            }
            return in_array($roleValue, [AccountType::EVENT_HOST->value, AccountType::ATTENDEE->value]);
        }

        return false;
    }

    /**
     * Check if account is suspended.
     */
    public function isSuspended(): bool
    {
        return $this->status === 'suspended';
    }

    /**
     * Check if account is active.
     */
    public function isActive(): bool
    {
        return $this->status === 'active';
    }

    /**
     * Check if email address is verified.
     */
    public function hasVerifiedEmail(): bool
    {
        return !is_null($this->email_verified_at);
    }

    /**
     * Mark the user's email address as verified.
     */
    public function markEmailAsVerified(): bool
    {
        $this->email_verified_at = now();
        return $this->save();
    }

    /**
     * Check if phone number is verified.
     */
    public function hasVerifiedPhone(): bool
    {
        return !is_null($this->phone_verified_at);
    }

    /**
     * Mark the user's phone number as verified.
     */
    public function markPhoneAsVerified(): bool
    {
        $this->phone_verified_at = now();
        return $this->save();
    }
}
