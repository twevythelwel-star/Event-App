<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    private const LOGO_URL = '/images/brand/eventbite-wordmark.webp';
    private const LEGACY_LOGO_URL = '/assets/island-events-logo.svg';

    public function up(): void
    {
        if (!Schema::hasTable('system_platform_settings')) {
            return;
        }

        // New rows default to the EventBite brand (the original create migration is left unchanged).
        Schema::table('system_platform_settings', function (Blueprint $table) {
            $table->string('platform_name')->default('EventBite')->change();
            $table->string('logo_url')->default(self::LOGO_URL)->change();
        });

        DB::table('system_platform_settings')
            ->where('id', 1)
            ->where('platform_name', 'Island Events & Ticketing')
            ->update([
                'platform_name' => 'EventBite',
                'logo_url' => self::LOGO_URL,
            ]);

        DB::table('system_platform_settings')
            ->where('logo_url', self::LEGACY_LOGO_URL)
            ->update(['logo_url' => self::LOGO_URL]);
    }

    public function down(): void
    {
        if (!Schema::hasTable('system_platform_settings')) {
            return;
        }

        Schema::table('system_platform_settings', function (Blueprint $table) {
            $table->string('platform_name')->default('Island Events & Ticketing')->change();
            $table->string('logo_url')->default(self::LEGACY_LOGO_URL)->change();
        });

        DB::table('system_platform_settings')
            ->where('id', 1)
            ->where('platform_name', 'EventBite')
            ->where('logo_url', self::LOGO_URL)
            ->update([
                'platform_name' => 'Island Events & Ticketing',
                'logo_url' => self::LEGACY_LOGO_URL,
            ]);

        DB::table('system_platform_settings')
            ->where('logo_url', self::LOGO_URL)
            ->update(['logo_url' => self::LEGACY_LOGO_URL]);
    }
};
