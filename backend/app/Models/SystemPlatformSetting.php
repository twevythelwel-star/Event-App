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
    ];

    protected $casts = [
        'supported_currencies' => 'array',
        'supported_payment_methods' => 'array',
        'mandatory_2fa_for_admins' => 'boolean',
        'admin_session_timeout_minutes' => 'integer',
        'attendee_session_timeout_days' => 'integer',
        'audit_retention_days' => 'integer',
        'pii_scrub_days' => 'integer',
        'database_pool_max' => 'integer',
        'queue_workers_count' => 'integer',
    ];
}
