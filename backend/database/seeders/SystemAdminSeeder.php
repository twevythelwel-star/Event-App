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
        // 1. Provision Root System Administrator (credentials come from .env, never from source control)
        $password = (string) config('events.system_admin.password', '');
        if ($password === '') {
            if (app()->environment('production')) {
                throw new \RuntimeException('Set SYSTEM_ADMIN_PASSWORD in .env before seeding the System Administrator in production.');
            }
            // Local development shares the demo accounts' well-known password
            $password = DemoAccountsSeeder::DEMO_PASSWORD;
        }

        SystemAdminProvisioningService::provisionInitialSystemAdmin([
            'name' => 'Platform Superadmin',
            'email' => (string) config('events.system_admin.email'),
            'password' => $password,
            'phone' => '+1 (305) 555-0100',
        ]);

        // 2. Initialize System Platform Defaults
        $settings = SystemPlatformSetting::firstOrCreate(
            ['id' => 1],
            [
                'platform_name' => 'EventBite',
                'logo_url' => '/images/brand/eventbite-wordmark.webp',
                'brand_theme' => 'eventbite_navy',
                'supported_currencies' => ['USD'],
                'supported_payment_methods' => ['cash', 'bank', 'zelle', 'cashapp', 'card'],
                'file_storage_status' => 'healthy',
                'storage_bucket' => 'eventbite-flyers-prod',
                'storage_provider' => 's3_compatible_r2',
                'notification_provider_status' => 'operational',
                'mandatory_2fa_for_admins' => true,
                'admin_session_timeout_minutes' => 30,
                'attendee_session_timeout_days' => 30,
                'default_timezone' => 'America/New_York',
                'default_locale' => 'en-US',
                'audit_retention_days' => 365,
                'pii_scrub_days' => 90,
                'database_pool_max' => 100,
                'queue_workers_count' => 4,
            ]
        );

        $legacyDefaults = [];
        if ($settings->supported_currencies === ['JMD', 'USD']) {
            $legacyDefaults['supported_currencies'] = ['USD'];
        }
        if ($settings->default_timezone === 'America/Jamaica') {
            $legacyDefaults['default_timezone'] = 'America/New_York';
        }
        if ($settings->default_locale === 'en-JM') {
            $legacyDefaults['default_locale'] = 'en-US';
        }
        if ($legacyDefaults !== []) {
            $settings->forceFill($legacyDefaults)->save();
        }
    }
}
