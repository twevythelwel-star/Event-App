<?php

namespace App\Services;

use App\Enums\AccountType;
use App\Models\AccountSetting;
use App\Models\HostEventSetting;
use App\Models\HostOperationalRule;
use App\Models\HostPaymentPolicy;
use App\Models\Order;
use App\Models\SystemPlatformSetting;
use App\Models\User;

class AccountSettingsService
{
    /**
     * Resolves settings accessible to a given user based on active context role.
     */
    public static function getSettingsForUser(User $user): array
    {
        $role = $user->getActiveRole();
        $allowedSettings = $role->getAllowedSettings();

        // 1. Common account profile & security
        $settings = [
            'role' => $role->value,
            'allowed_settings' => $allowedSettings,
            'profile' => [
                'name' => $user->name,
                'email' => $user->email,
                'phone' => CredentialMaskingService::maskPhoneNumber($user->phone ?? ''),
                'raw_phone' => $user->phone,
                'status' => $user->status,
                'preferred_display_currency' => $user->preferred_display_currency ?? 'JMD',
                'is_2fa_enabled' => !empty($user->two_factor_confirmed_at),
                'is_email_verified' => !empty($user->email_verified_at),
                'is_phone_verified' => !empty($user->phone_verified_at),
            ],
        ];

        // 2. System Administrator Settings
        if ($role === AccountType::SYSTEM_ADMIN) {
            $sys = SystemPlatformSetting::first();
            $settings['system_platform'] = [
                'platform_name' => $sys->platform_name ?? 'Island Events & Ticketing',
                'supported_currencies' => $sys->supported_currencies ?? ['JMD', 'USD'],
                'supported_payment_methods' => $sys->supported_payment_methods ?? ['cash', 'bank', 'zelle', 'cashapp', 'card'],
                'file_storage_status' => $sys->file_storage_status ?? 'healthy',
                'storage_bucket' => $sys->storage_bucket ?? 'island-events-flyers-prod',
                'storage_provider' => $sys->storage_provider ?? 's3_compatible_r2',
                'notification_provider_status' => $sys->notification_provider_status ?? 'operational',
                'mandatory_2fa_for_admins' => $sys->mandatory_2fa_for_admins ?? true,
                'admin_session_timeout_minutes' => $sys->admin_session_timeout_minutes ?? 30,
                'default_timezone' => $sys->default_timezone ?? 'America/Jamaica',
                'audit_retention_days' => $sys->audit_retention_days ?? 365,
                'pii_scrub_days' => $sys->pii_scrub_days ?? 90,
                'database_pool_max' => $sys->database_pool_max ?? 100,
                'queue_workers_count' => $sys->queue_workers_count ?? 4,
            ];
        }

        // 3. Administrator Scoped Settings
        if ($role === AccountType::ADMINISTRATOR) {
            $perm = $user->adminPermissions;
            $settings['admin_scope'] = [
                'scope' => $perm->scope ?? 'assigned_events',
                'can_manage_events' => $perm->can_manage_events ?? false,
                'can_moderate_content' => $perm->can_moderate_content ?? false,
                'can_support_orders' => $perm->can_support_orders ?? true,
                'can_view_payments' => $perm->can_view_payments ?? false,
                'can_view_reports' => $perm->can_view_reports ?? false,
                'can_access_audit_logs' => $perm->can_access_audit_logs ?? false,
            ];
        }

        return $settings;
    }

    /**
     * Updates attendee display currency preference.
     * INVARIANT: The preferred display currency must NOT change the transaction currency or historical totals.
     */
    public static function updateAttendeeDisplayCurrency(User $user, string $newCurrency): array
    {
        $currency = strtoupper(trim($newCurrency));
        if (!in_array($currency, ['JMD', 'USD'])) {
            throw new \InvalidArgumentException('Unsupported display currency. Choose JMD or USD.');
        }

        $user->preferred_display_currency = $currency;
        $user->save();

        AuditLogService::log(
            action: 'display_currency_preference_updated',
            targetEntity: 'users',
            targetId: (string) $user->id,
            details: "User updated display currency preference to {$currency}. Transaction currencies remain untouched.",
            actor: $user
        );

        return [
            'success' => true,
            'preferred_display_currency' => $currency,
            'invariant_guarantee' => 'Display preference only. Historical and future charged transaction currencies are governed solely by event configuration.',
        ];
    }

    /**
     * Converts order amount for informational display preview only.
     * Invariant: Never modifies order model or database values.
     */
    public static function formatDisplayEstimate(Order|array $order, string $preferredCurrency, float $exchangeRate = 158.0): array
    {
        $chargedCurrency = is_array($order) ? ($order['currency'] ?? 'JMD') : $order->currency;
        $chargedAmount = is_array($order) ? ($order['total_amount'] ?? 0.0) : (float) $order->total_amount;

        $estimatedAmount = $chargedAmount;
        $isEstimate = false;

        if ($chargedCurrency !== $preferredCurrency) {
            $isEstimate = true;
            if ($chargedCurrency === 'USD' && $preferredCurrency === 'JMD') {
                $estimatedAmount = $chargedAmount * $exchangeRate;
            } else {
                $estimatedAmount = $chargedAmount / $exchangeRate;
            }
        }

        return [
            'actual_charged_currency' => $chargedCurrency,
            'actual_charged_amount' => $chargedAmount,
            'display_formatted_estimate' => sprintf('~%s $%.2f', $preferredCurrency, $estimatedAmount),
            'is_conversion_estimate_only' => $isEstimate,
        ];
    }

    /**
     * Updates host event defaults.
     * INVARIANT: Modifying event defaults never alters historical orders.
     */
    public static function updateHostEventDefaults(string $organizerId, array $data, User $actor): HostEventSetting
    {
        $settings = HostEventSetting::firstOrNew(['organizer_id' => $organizerId]);

        if (isset($data['default_currency']) && in_array(strtoupper($data['default_currency']), ['JMD', 'USD'])) {
            $settings->default_currency = strtoupper($data['default_currency']);
        }
        if (!empty($data['default_timezone'])) {
            $settings->default_timezone = trim($data['default_timezone']);
        }
        if (!empty($data['default_location'])) {
            $settings->default_location = trim($data['default_location']);
        }
        if (isset($data['default_order_deadline_hours'])) {
            $settings->default_order_deadline_hours = max(1, (int) $data['default_order_deadline_hours']);
        }
        if (!empty($data['default_pickup_instructions'])) {
            $settings->default_pickup_instructions = trim($data['default_pickup_instructions']);
        }
        if (!empty($data['default_flyer_aspect_ratio'])) {
            $settings->default_flyer_aspect_ratio = trim($data['default_flyer_aspect_ratio']);
        }
        if (!empty($data['default_brand_theme'])) {
            $settings->default_brand_theme = trim($data['default_brand_theme']);
        }

        $settings->save();

        AuditLogService::log(
            action: 'host_event_defaults_updated',
            targetEntity: 'host_event_settings',
            targetId: (string) $settings->id,
            details: "Host updated event defaults for organizer {$organizerId}. Existing orders remain immutable.",
            actor: $actor
        );

        return $settings;
    }
}
