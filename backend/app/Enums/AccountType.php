<?php

namespace App\Enums;

enum AccountType: string
{
    case SYSTEM_ADMIN = 'system_admin';
    case ADMINISTRATOR = 'admin';
    case EVENT_HOST = 'event_host';
    case ATTENDEE = 'attendee';

    /**
     * Human-readable label for dashboard display.
     */
    public function label(): string
    {
        return match ($this) {
            self::SYSTEM_ADMIN => 'System Administrator',
            self::ADMINISTRATOR => 'Administrator (Delegated)',
            self::EVENT_HOST => 'Event Host',
            self::ATTENDEE => 'Attendee',
        };
    }

    /**
     * Primary purpose description per enterprise specification.
     */
    public function primaryPurpose(): string
    {
        return match ($this) {
            self::SYSTEM_ADMIN => 'Operates the entire platform',
            self::ADMINISTRATOR => 'Manages authorized platform operations',
            self::EVENT_HOST => 'Creates and operates events',
            self::ATTENDEE => 'Purchases tickets and food',
        };
    }

    /**
     * Whether this account can access platform-wide configuration, storage/queue health, and global fees.
     */
    public function canAccessPlatformManagement(): bool
    {
        return $this === self::SYSTEM_ADMIN;
    }

    /**
     * Whether this account can access the delegated operations dashboard (event oversight, disputes, host support).
     */
    public function canAccessDelegatedAdmin(): bool
    {
        return in_array($this, [self::SYSTEM_ADMIN, self::ADMINISTRATOR]);
    }

    /**
     * Delegated Administrators must NOT automatically receive every System Administrator privilege.
     * Their access is strictly assigned.
     */
    public function canModifyGlobalPlatformFees(): bool
    {
        return $this === self::SYSTEM_ADMIN;
    }

    /**
     * Sensitive credentials must be protected and never displayed in plaintext.
     * Even privileged accounts must receive masked credentials.
     */
    public function canViewUnmaskedCredentials(): bool
    {
        return false; // Universal enterprise security rule
    }

    /**
     * Access to payment records and personal information should be controlled and audited, even for privileged accounts.
     */
    public function requiresPiiAuditLogging(): bool
    {
        return in_array($this, [self::SYSTEM_ADMIN, self::ADMINISTRATOR]);
    }

    /**
     * Whether this account operates own events (flyers, menus, stock limits, cash receipts, staff).
     */
    public function canOperateOwnEvents(): bool
    {
        return $this === self::EVENT_HOST;
    }

    /**
     * Whether this account purchases food and stores digital tickets.
     */
    public function isAttendee(): bool
    {
        return $this === self::ATTENDEE;
    }

    /**
     * Returns the authorized access scope for a given setting under this account type,
     * or null if the setting is not available to this account.
     */
    public function getSettingAccess(string $settingKey): ?string
    {
        return match ($settingKey) {
            'personal_profile', 'change_password', 'two_factor_auth', 'notification_preferences' => 'all',
            'manage_platform_users' => match ($this) {
                self::SYSTEM_ADMIN => 'all',
                self::ADMINISTRATOR => 'limited',
                default => null,
            },
            'manage_platform_roles' => match ($this) {
                self::SYSTEM_ADMIN => 'all',
                self::ADMINISTRATOR => 'assigned_permissions_only',
                default => null,
            },
            'platform_configuration' => match ($this) {
                self::SYSTEM_ADMIN => 'all',
                default => null,
            },
            'event_management' => match ($this) {
                self::SYSTEM_ADMIN => 'all_subject_to_policy',
                self::ADMINISTRATOR => 'authorized_scope',
                self::EVENT_HOST => 'own_and_assigned_events',
                default => null,
            },
            'flyer_menu_management' => match ($this) {
                self::SYSTEM_ADMIN => 'oversight',
                self::ADMINISTRATOR => 'authorized_scope',
                self::EVENT_HOST => 'own_and_assigned_events',
                default => null,
            },
            'event_payment_instructions' => match ($this) {
                self::SYSTEM_ADMIN => 'platform_defaults',
                self::ADMINISTRATOR => 'authorized_scope',
                self::EVENT_HOST => 'own_events',
                default => null,
            },
            'record_verify_payments' => match ($this) {
                self::SYSTEM_ADMIN => 'authorized_oversight',
                self::ADMINISTRATOR => 'assigned_permissions',
                self::EVENT_HOST => 'own_events',
                default => null,
            },
            'manage_event_staff' => match ($this) {
                self::SYSTEM_ADMIN => 'oversight',
                self::ADMINISTRATOR => 'authorized_scope',
                self::EVENT_HOST => 'own_events',
                default => null,
            },
            'manage_tickets_collection' => match ($this) {
                self::SYSTEM_ADMIN => 'oversight',
                self::ADMINISTRATOR => 'authorized_scope',
                self::EVENT_HOST => 'own_events',
                self::ATTENDEE => 'own_tickets',
            },
            'sales_financial_reports' => match ($this) {
                self::SYSTEM_ADMIN => 'authorized_oversight',
                self::ADMINISTRATOR => 'assigned_scope',
                self::EVENT_HOST => 'own_events',
                self::ATTENDEE => 'own_transactions',
            },
            'currency_regional_preferences' => match ($this) {
                self::SYSTEM_ADMIN => 'platform_defaults',
                self::ADMINISTRATOR => 'assigned_scope',
                self::EVENT_HOST => 'event_specific_settings',
                self::ATTENDEE => 'personal_preference',
            },
            'privacy_data_controls' => match ($this) {
                self::SYSTEM_ADMIN => 'platform_policies',
                self::ADMINISTRATOR => 'assigned_permissions',
                self::EVENT_HOST => 'own_account_lawful_event_data',
                self::ATTENDEE => 'own_account_personal_data',
            },
            'audit_security_logs' => match ($this) {
                self::SYSTEM_ADMIN => 'authorized_platform_wide_access',
                self::ADMINISTRATOR => 'scoped_access',
                self::EVENT_HOST => 'own_event_activity',
                self::ATTENDEE => 'own_account_activity',
            },
            default => null,
        };
    }

    /**
     * Whether a setting should be displayed and accessible in this account's settings menu.
     */
    public function canAccessSetting(string $settingKey): bool
    {
        return $this->getSettingAccess($settingKey) !== null;
    }

    /**
     * Returns the complete array of settings keys accessible to this account.
     */
    public function getAllowedSettings(): array
    {
        $allSettings = [
            'personal_profile',
            'change_password',
            'two_factor_auth',
            'notification_preferences',
            'manage_platform_users',
            'manage_platform_roles',
            'platform_configuration',
            'event_management',
            'flyer_menu_management',
            'event_payment_instructions',
            'record_verify_payments',
            'manage_event_staff',
            'manage_tickets_collection',
            'sales_financial_reports',
            'currency_regional_preferences',
            'privacy_data_controls',
            'audit_security_logs',
        ];

        return array_values(array_filter($allSettings, fn ($s) => $this->canAccessSetting($s)));
    }
}
