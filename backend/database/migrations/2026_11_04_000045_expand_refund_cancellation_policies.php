<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        foreach (['events', 'host_payment_policies'] as $tableName) {
            Schema::table($tableName, function (Blueprint $table) {
                $table->string('refund_policy_mode', 32)->default('manual_full_and_partial');
                $table->string('host_cancellation_policy', 40)->default('contact_organizer');
                $table->string('postponement_policy', 40)->default('tickets_valid_for_new_date');
                $table->string('weather_cancellation_policy', 40)->default('contact_organizer');
                $table->unsignedSmallInteger('food_order_cancellation_deadline_hours')->nullable();
                $table->unsignedSmallInteger('food_order_modification_deadline_hours')->nullable();
                $table->json('non_refundable_ticket_categories')->nullable();
            });
        }
    }

    public function down(): void
    {
        foreach (['host_payment_policies', 'events'] as $tableName) {
            Schema::table($tableName, function (Blueprint $table) {
                $table->dropColumn([
                    'refund_policy_mode', 'host_cancellation_policy', 'postponement_policy',
                    'weather_cancellation_policy', 'food_order_cancellation_deadline_hours',
                    'food_order_modification_deadline_hours', 'non_refundable_ticket_categories',
                ]);
            });
        }
    }
};