<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Additive only (expand phase):
     * - orders.idempotency_key with a unique index per event, so two identical checkout submissions
     *   cannot both create an order (previously a LIKE search on notes with no constraint).
     * - order_items.ticket_tier_id records which host-configured ticket type a ticket line came from.
     *   Ticket lines are not menu items, so menu_item_id (a foreign key to menu_items) stays null for them.
     */
    public function up(): void
    {
        Schema::table('orders', function (Blueprint $table) {
            if (!Schema::hasColumn('orders', 'idempotency_key')) {
                $table->string('idempotency_key', 120)->nullable()->after('secure_ticket_token');
                $table->unique(['event_id', 'idempotency_key']);
            }
        });

        Schema::table('order_items', function (Blueprint $table) {
            if (!Schema::hasColumn('order_items', 'ticket_tier_id')) {
                $table->string('ticket_tier_id', 64)->nullable()->after('menu_item_id');
            }
        });
    }

    public function down(): void
    {
        Schema::table('orders', function (Blueprint $table) {
            $table->dropUnique(['event_id', 'idempotency_key']);
            $table->dropColumn('idempotency_key');
        });

        Schema::table('order_items', function (Blueprint $table) {
            $table->dropColumn('ticket_tier_id');
        });
    }
};
