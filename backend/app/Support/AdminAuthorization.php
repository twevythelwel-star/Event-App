<?php

declare(strict_types=1);

namespace App\Support;

use App\Enums\AccountType;
use App\Models\Event;
use App\Models\Order;
use App\Models\SystemPlatformSetting;
use App\Models\User;
use App\Policies\SettingsPolicy;
use App\Services\OrderOperationsService;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\Gate;

/**
 * Single source of truth for the admin console's permissions.
 *
 * Built on the existing model: AccountType (system_admin / admin), the per-administrator
 * AdminPermission row (capability flags + organizer scope), UserPolicy / SettingsPolicy and
 * OrderOperationsService capabilities. Controllers and views ask Gate abilities defined here
 * instead of checking role names themselves.
 *
 * Safe defaults: a delegated administrator without an admin_permissions row can open the
 * console overview and nothing else.
 */
final class AdminAuthorization
{
    public const ACCESS = 'admin.access';
    public const USERS_VIEW = 'admin.users.view';
    public const USER_VIEW = 'admin.users.view-one';
    public const USER_CONTACT = 'admin.users.view-contact';
    public const USER_STATUS = 'admin.users.manage-status';
    public const ROLES_VIEW = 'admin.roles.view';
    public const ROLES_MANAGE = 'admin.roles.manage';
    public const EVENTS_VIEW = 'admin.events.view';
    public const EVENT_VIEW = 'admin.events.view-one';
    public const EVENT_MODERATE = 'admin.events.moderate';
    public const PAYMENTS_VIEW = 'admin.payments.view';
    public const ORDER_VIEW = 'admin.orders.view-one';
    public const ORDER_CUSTOMER = 'admin.orders.view-customer';
    public const PAYMENT_REVIEW = 'admin.payments.review';
    public const REPORTS_VIEW = 'admin.reports.view';
    public const REPORTS_FINANCIAL = 'admin.reports.financial';
    public const REPORTS_EXPORT = 'admin.reports.export';
    public const AUDIT_VIEW = 'admin.audit.view';
    public const SETTINGS_MANAGE = 'admin.settings.manage';

    // Wallet provider status (platform-wide configuration and pass operations): system administrators only.
    public const WALLETS_VIEW = 'admin.wallets.view';
    // Compliance management (the compliance dashboard, review queues, suspensions, safety reports, policy drafts and
    // the US state and local compliance database): system administrators, and delegated administrators granted
    // "Compliance management". Approving database records and publishing policies stays with system administrators.
    public const COMPLIANCE_MANAGE = 'admin.compliance.manage';
    // Host identity documents: only administrators specifically granted access (admin_permissions
    // can_access_identity_documents), whatever their role. A system administrator grants it, never to themselves.
    public const IDENTITY_DOCUMENTS = 'admin.compliance.identity-documents';
    public const IDENTITY_ACCESS_GRANT = 'admin.compliance.identity-access-grant';
    // Consent records (policy acceptances with IP and browser) and the compliance audit trail.
    public const COMPLIANCE_RECORDS = 'admin.compliance.records';
    public const COMPLIANCE_EXPORT = 'admin.compliance.export';
    public const COMPLIANCE_SUSPEND_HOST = 'admin.compliance.suspend-host';
    public const COMPLIANCE_SUSPEND_EVENT = 'admin.compliance.suspend-event';
    public const POLICY_PUBLISH = 'admin.compliance.policy-publish';
    public const COMPLIANCE_VIEW = 'admin.compliance.view';
    // The event photography library: administrators who moderate content add photos; a system administrator other
    // than the uploader approves each one against the photography standards before it can be shown.
    public const PHOTO_LIBRARY_MANAGE = 'admin.photo-library.manage';
    public const PHOTO_LIBRARY_APPROVE = 'admin.photo-library.approve';
    public const COMPLIANCE_EDIT = 'admin.compliance.edit';
    public const COMPLIANCE_APPROVE = 'admin.compliance.approve';
    // Overriding an event publication gate: system administrators only, always with a reason, an expiry and an audit entry.
    public const COMPLIANCE_OVERRIDE = 'admin.compliance.override';

    public static function register(): void
    {
        // SettingsPolicy guards platform configuration and admin permission grants; attach it to the
        // platform settings model so Gate checks reach it.
        Gate::policy(SystemPlatformSetting::class, SettingsPolicy::class);

        Gate::define(self::ACCESS, fn (User $user) => self::isActiveAdmin($user));

        Gate::define(self::USERS_VIEW, fn (User $user) => self::isActiveAdmin($user) && Gate::forUser($user)->allows('viewAny', User::class));
        Gate::define(self::USER_VIEW, fn (User $user, User $target) => Gate::forUser($user)->allows(self::USERS_VIEW)
            && Gate::forUser($user)->allows('view', $target)
            && self::userInScope($user, $target));
        // Unmasked phone numbers are only for platform owners; delegated admins work from email.
        Gate::define(self::USER_CONTACT, fn (User $user) => self::isSystemAdmin($user));
        Gate::define(self::USER_STATUS, fn (User $user, User $target) => self::isActiveAdmin($user) && Gate::forUser($user)->allows('manageStatus', $target));

        Gate::define(self::ROLES_VIEW, fn (User $user) => self::isActiveAdmin($user));
        Gate::define(self::ROLES_MANAGE, fn (User $user, ?User $target = null) => self::isActiveAdmin($user)
            && ($target === null
                ? self::isSystemAdmin($user)
                : $user->id !== $target->id && Gate::forUser($user)->allows('grantAdminPermissions', [SystemPlatformSetting::class, $target])));

        Gate::define(self::EVENTS_VIEW, fn (User $user) => self::isSystemAdmin($user)
            || (self::isActiveAdmin($user) && (self::flag($user, 'can_manage_events') || self::flag($user, 'can_moderate_content'))));
        Gate::define(self::EVENT_VIEW, fn (User $user, Event $event) => Gate::forUser($user)->allows(self::EVENTS_VIEW)
            && self::organizerInScope($user, $event->organizer_id));
        Gate::define(self::EVENT_MODERATE, fn (User $user, Event $event) => self::isActiveAdmin($user)
            && (self::isSystemAdmin($user) || self::flag($user, 'can_moderate_content'))
            && self::organizerInScope($user, $event->organizer_id));

        Gate::define(self::PAYMENTS_VIEW, fn (User $user) => self::isSystemAdmin($user)
            || (self::isActiveAdmin($user) && self::flag($user, 'can_view_payments')));
        Gate::define(self::ORDER_VIEW, fn (User $user, Order $order) => Gate::forUser($user)->allows(self::PAYMENTS_VIEW)
            && self::organizerInScope($user, $order->organizer_id));
        Gate::define(self::ORDER_CUSTOMER, fn (User $user, Order $order) => self::isSystemAdmin($user));
        // Verification stays with whoever the order operations model already trusts (system admins,
        // event owners, payment verifiers); a delegated admin flag never grants it.
        Gate::define(self::PAYMENT_REVIEW, fn (User $user, Order $order) => Gate::forUser($user)->allows(self::ORDER_VIEW, $order)
            && app(OrderOperationsService::class)->capabilities($user, $order)['verify']);

        Gate::define(self::REPORTS_VIEW, fn (User $user) => self::isSystemAdmin($user)
            || (self::isActiveAdmin($user) && self::flag($user, 'can_view_reports')));
        Gate::define(self::REPORTS_FINANCIAL, fn (User $user) => Gate::forUser($user)->allows(self::REPORTS_VIEW)
            && Gate::forUser($user)->allows(self::PAYMENTS_VIEW));
        Gate::define(self::REPORTS_EXPORT, fn (User $user) => Gate::forUser($user)->allows(self::REPORTS_FINANCIAL));

        Gate::define(self::AUDIT_VIEW, fn (User $user) => self::isSystemAdmin($user)
            || (self::isActiveAdmin($user) && self::flag($user, 'can_access_audit_logs')));

        Gate::define(self::COMPLIANCE_MANAGE, fn (User $user) => self::isComplianceManager($user));
        Gate::define(self::COMPLIANCE_VIEW, fn (User $user) => self::isComplianceManager($user));
        Gate::define(self::COMPLIANCE_EDIT, fn (User $user) => self::isComplianceManager($user));
        Gate::define(self::IDENTITY_DOCUMENTS, fn (User $user) => self::canAccessIdentityDocuments($user));
        Gate::define(self::IDENTITY_ACCESS_GRANT, fn (User $user, User $target) => self::isSystemAdmin($user)
            && $user->id !== $target->id && self::isActiveAdmin($target));
        Gate::define(self::COMPLIANCE_RECORDS, fn (User $user) => self::isComplianceManager($user)
            && (self::isSystemAdmin($user) || self::flag($user, 'can_access_audit_logs')));
        Gate::define(self::COMPLIANCE_EXPORT, fn (User $user) => self::isComplianceManager($user));
        Gate::define(self::COMPLIANCE_SUSPEND_HOST, fn (User $user, User $host) => self::isComplianceManager($user)
            && $user->id !== $host->id && self::hostInScope($user, $host));
        Gate::define(self::COMPLIANCE_SUSPEND_EVENT, fn (User $user, Event $event) => self::isComplianceManager($user)
            && self::organizerInScope($user, $event->organizer_id));
        Gate::define(self::POLICY_PUBLISH, fn (User $user) => self::isSystemAdmin($user));
        Gate::define(self::PHOTO_LIBRARY_MANAGE, fn (User $user) => self::isSystemAdmin($user)
            || (self::isActiveAdmin($user) && self::flag($user, 'can_moderate_content')));
        Gate::define(self::PHOTO_LIBRARY_APPROVE, fn (User $user, ?\App\Models\LibraryPhoto $photo = null) => self::isSystemAdmin($user)
            && ($photo === null || $photo->uploaded_by !== $user->id));

        Gate::policy(\App\Models\HostIdentityVerification::class, \App\Policies\HostIdentityVerificationPolicy::class);
        Gate::policy(\App\Models\EventComplianceEvidence::class, \App\Policies\EventComplianceEvidencePolicy::class);
        Gate::policy(\App\Models\Compliance\ComplianceSafetyReport::class, \App\Policies\ComplianceSafetyReportPolicy::class);
        Gate::policy(\App\Models\Compliance\CompliancePolicyVersion::class, \App\Policies\CompliancePolicyVersionPolicy::class);
        Gate::define(self::COMPLIANCE_APPROVE, fn (User $user) => self::isSystemAdmin($user));
        Gate::define(self::COMPLIANCE_OVERRIDE, fn (User $user) => self::isSystemAdmin($user));

        Gate::define(self::WALLETS_VIEW, fn (User $user) => self::isActiveAdmin($user) && self::isSystemAdmin($user));

        Gate::define(self::SETTINGS_MANAGE, fn (User $user) => self::isActiveAdmin($user)
            && Gate::forUser($user)->allows('updatePlatformConfiguration', SystemPlatformSetting::class));
    }

    public static function isSystemAdmin(User $user): bool
    {
        return $user->isActive() && $user->hasRole(AccountType::SYSTEM_ADMIN);
    }

    /** A system administrator, or a delegated administrator granted "Compliance management". */
    public static function isComplianceManager(User $user): bool
    {
        return self::isSystemAdmin($user) || (self::isActiveAdmin($user) && self::flag($user, 'can_manage_compliance'));
    }

    /** Explicitly granted, for every role: being a system administrator alone does not open identity documents. */
    public static function canAccessIdentityDocuments(User $user): bool
    {
        return self::isActiveAdmin($user) && self::flag($user, 'can_access_identity_documents');
    }

    /** A host is in an administrator's scope when one of the host's organizers is. */
    public static function hostInScope(User $admin, User $host): bool
    {
        $scope = self::organizerScope($admin);
        if ($scope === null) {
            return true;
        }

        return \App\Models\OrganizerUser::where('user_id', $host->id)->whereIn('organizer_id', $scope)->exists();
    }

    public static function isActiveAdmin(User $user): bool
    {
        return $user->isActive()
            && ($user->hasRole(AccountType::SYSTEM_ADMIN) || $user->hasRole(AccountType::ADMINISTRATOR));
    }

    /**
     * Organizer ids this administrator may see, or null for platform-wide access.
     *
     * @return array<int, string>|null
     */
    public static function organizerScope(User $user): ?array
    {
        if (self::isSystemAdmin($user)) {
            return null;
        }

        $permissions = $user->adminPermissions;
        if (!$permissions) {
            return [];
        }
        if ($permissions->scope === 'all') {
            return null;
        }

        return array_values(array_map('strval', $permissions->assigned_organizer_ids ?? []));
    }

    public static function organizerInScope(User $user, ?string $organizerId): bool
    {
        $scope = self::organizerScope($user);

        return $scope === null || ($organizerId !== null && in_array($organizerId, $scope, true));
    }

    /**
     * Restrict a query to the administrator's organizers.
     */
    public static function applyOrganizerScope(Builder $query, User $user, string $column = 'organizer_id'): Builder
    {
        $scope = self::organizerScope($user);
        if ($scope === null) {
            return $query;
        }

        return $scope === [] ? $query->whereRaw('1 = 0') : $query->whereIn($column, $scope);
    }

    /**
     * Restrict a user query to people connected to the administrator's organizers
     * (members, event staff or customers). Platform-wide administrators see everyone.
     */
    public static function applyUserScope(Builder $query, User $user): Builder
    {
        $scope = self::organizerScope($user);
        if ($scope === null) {
            return $query;
        }
        if ($scope === []) {
            return $query->whereRaw('1 = 0');
        }

        return $query->where(function (Builder $q) use ($scope) {
            $q->whereIn('id', fn ($sub) => $sub->select('user_id')->from('organizer_users')->whereIn('organizer_id', $scope))
                ->orWhereIn('id', fn ($sub) => $sub->select('user_id')->from('event_worker_assignments')->whereIn('organizer_id', $scope))
                ->orWhereIn('id', fn ($sub) => $sub->select('user_id')->from('orders')->whereNotNull('user_id')->whereIn('organizer_id', $scope));
        });
    }

    /**
     * Scoped administrators only see audit records tied to their organizers;
     * platform-level records (no organizer) stay with platform-wide administrators.
     */
    public static function applyAuditScope(Builder $query, User $user): Builder
    {
        return self::applyOrganizerScope($query, $user, 'organizer_id');
    }

    public static function userInScope(User $admin, User $target): bool
    {
        if (self::organizerScope($admin) === null) {
            return true;
        }

        return self::applyUserScope(User::query()->whereKey($target->id), $admin)->exists();
    }

    private static function flag(User $user, string $flag): bool
    {
        return (bool) ($user->adminPermissions?->{$flag} ?? false);
    }
}
