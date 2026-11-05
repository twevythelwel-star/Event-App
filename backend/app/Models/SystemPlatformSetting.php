<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class SystemPlatformSetting extends Model
{
    use HasFactory;

    protected $table = 'system_platform_settings';

    protected $fillable = [
        'platform_name',
        'logo_url',
        'brand_theme',
        'supported_currencies',
        'supported_payment_methods',
        'payment_processors',
        'file_storage_status',
        'storage_bucket',
        'storage_provider',
        'notification_provider_status',
        'mandatory_2fa_for_admins',
        'admin_session_timeout_minutes',
        'attendee_session_timeout_days',
        'default_timezone',
        'default_locale',
        'audit_retention_days',
        'pii_scrub_days',
        'database_pool_max',
        'queue_workers_count',
        // QR code configuration
        'qr_design_version',
        'qr_default_preset',
        'qr_enforce_design_rules',
        // Notification settings
        'email_notifications_enabled',
        'sms_notifications_enabled',
        'push_notifications_enabled',
        // Fraud prevention
        'fraud_detection_enabled',
        'max_orders_per_ip_hour',
        'max_amount_per_order_minor',
        'require_3ds_above_minor',
        // Feature flags
        'feature_flags',
    ];

    protected $casts = [
        'supported_currencies' => 'array',
        'supported_payment_methods' => 'array',
        'payment_processors' => 'array',
        'mandatory_2fa_for_admins' => 'boolean',
        'admin_session_timeout_minutes' => 'integer',
        'attendee_session_timeout_days' => 'integer',
        'audit_retention_days' => 'integer',
        'pii_scrub_days' => 'integer',
        'database_pool_max' => 'integer',
        'queue_workers_count' => 'integer',
        'qr_design_version' => 'integer',
        'qr_default_preset' => 'string',
        'qr_enforce_design_rules' => 'boolean',
        'email_notifications_enabled' => 'boolean',
        'sms_notifications_enabled' => 'boolean',
        'push_notifications_enabled' => 'boolean',
        'fraud_detection_enabled' => 'boolean',
        'max_orders_per_ip_hour' => 'integer',
        'max_amount_per_order_minor' => 'integer',
        'require_3ds_above_minor' => 'integer',
        'feature_flags' => 'array',
    ];
}
