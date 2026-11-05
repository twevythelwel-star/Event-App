<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Platform-wide operational switches that have no home yet: QR design rules, which notification channels are on,
 * fraud limits and feature flags. Kept on the one platform settings row.
 *
 * Deliberately not here, because the schema already holds them: payment processor approval
 * (payment_processors, added by 000021), platform fees and taxes (charge_rules), and host approval
 * (host_applications).
 */
return new class extends Migration
{
    private const COLUMNS = [
        'qr_design_version', 'qr_default_preset', 'qr_enforce_design_rules',
        'email_notifications_enabled', 'sms_notifications_enabled', 'push_notifications_enabled',
        'fraud_detection_enabled', 'max_orders_per_ip_hour', 'max_amount_per_order_minor', 'require_3ds_above_minor',
        'feature_flags',
    ];

    public function up(): void
    {
        Schema::table('system_platform_settings', function (Blueprint $table) {
            $table->unsignedSmallInteger('qr_design_version')->default(1);
            $table->string('qr_default_preset', 32)->default('geometric');
            $table->boolean('qr_enforce_design_rules')->default(true);

            $table->boolean('email_notifications_enabled')->default(true);
            $table->boolean('sms_notifications_enabled')->default(true);
            $table->boolean('push_notifications_enabled')->default(false);

            $table->boolean('fraud_detection_enabled')->default(true);
            $table->unsignedInteger('max_orders_per_ip_hour')->default(10);
            $table->unsignedBigInteger('max_amount_per_order_minor')->default(1000000); // $10,000.00
            $table->unsignedBigInteger('require_3ds_above_minor')->default(500000);     // $5,000.00

            $table->json('feature_flags')->nullable();
        });
    }

    public function down(): void
    {
        Schema::table('system_platform_settings', fn (Blueprint $table) => $table->dropColumn(self::COLUMNS));
    }
};
