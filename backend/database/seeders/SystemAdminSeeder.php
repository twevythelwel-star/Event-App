<?php

namespace Database\Seeders;

use App\Enums\AccountType;
use App\Models\SystemPlatformSetting;
use App\Services\SystemAdminProvisioningService;
use Illuminate\Database\Seeder;

class SystemAdminSeeder extends Seeder
{
    /**
     * Seed initial root System Administrator and Platform Platform Defaults.
     * Never accessible through public registration.
     */
    public function run(): void
    {
        // 1. Provision Root System Administrator
        SystemAdminProvisioningService::provisionInitialSystemAdmin([
            'name' => 'Platform Superadmin',
            'email' => 'superadmin@islandevents.example',
            'password' => 'SecureIslandSuperPass2026!#',
            'phone' => '+1 (876) 555-0100',
        ]);

        // 2. Initialize System Platform Defaults
        SystemPlatformSetting::firstOrCreate(
            ['id' => 1],
            [
                'platform_name' => 'Island Events & Ticketing',
                'logo_url' => '/assets/island-events-logo.svg',
                'brand_theme' => 'caribbean_emerald',
                'supported_currencies' => ['JMD', 'USD'],
                'supported_payment_methods' => ['cash', 'bank', 'zelle', 'cashapp', 'card'],
                'file_storage_status' => 'healthy',
                'storage_bucket' => 'island-events-flyers-prod',
                'storage_provider' => 's3_compatible_r2',
                'notification_provider_status' => 'operational',
                'mandatory_2fa_for_admins' => true,
                'admin_session_timeout_minutes' => 30,
                'attendee_session_timeout_days' => 30,
                'default_timezone' => 'America/Jamaica',
                'default_locale' => 'en-JM',
                'audit_retention_days' => 365,
                'pii_scrub_days' => 90,
                'database_pool_max' => 100,
                'queue_workers_count' => 4,
            ]
        );
    }
}
