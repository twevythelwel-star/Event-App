<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    /**
     * Adds Guided Event-Creation Wizard fields to events:
     * - category (e.g., "Seafood & Fish Fry", "Reggae & Live Music", "Cultural Festival")
     * - event_date_iso (YYYY-MM-DD in event's IANA timezone)
     * - start_time / end_time (HH:MM 24h or display time)
     * - supports_food_ordering (boolean, defaults to true)
     * - ticket_tiers (JSON array of configured ticket types, prices, quantity limits, and sale periods)
     */
    public function up(): void
    {
        Schema::table('events', function (Blueprint $table) {
            if (!Schema::hasColumn('events', 'category')) {
                $table->string('category', 128)->nullable()->after('tagline');
            }
            if (!Schema::hasColumn('events', 'event_date_iso')) {
                $table->string('event_date_iso', 32)->nullable()->after('date_str');
            }
            if (!Schema::hasColumn('events', 'start_time')) {
                $table->string('start_time', 32)->nullable()->after('time_str');
            }
            if (!Schema::hasColumn('events', 'end_time')) {
                $table->string('end_time', 32)->nullable()->after('start_time');
            }
            if (!Schema::hasColumn('events', 'supports_food_ordering')) {
                $table->boolean('supports_food_ordering')->default(true)->after('packaging_fee_jmd');
            }
            if (!Schema::hasColumn('events', 'ticket_tiers')) {
                $table->json('ticket_tiers')->nullable()->after('supports_food_ordering');
            }
        });
    }

    public function down(): void
    {
        Schema::table('events', function (Blueprint $table) {
            $columns = array_filter(
                ['category', 'event_date_iso', 'start_time', 'end_time', 'supports_food_ordering', 'ticket_tiers'],
                fn(string $col) => Schema::hasColumn('events', $col)
            );
            if (!empty($columns)) {
                $table->dropColumn($columns);
            }
        });
    }
};

