<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Indexes for the order history screens: an organizer's and a customer's orders newest first, an order's food
 * lines, and an order's charges by kind and currency. Forward-only and data-free; added as a new migration
 * because 000033 has already run on existing databases.
 */
return new class extends Migration
{
    private const INDEXES = [
        'orders' => [
            'orders_org_created_idx' => ['organizer_id', 'created_at'],
            'orders_user_created_idx' => ['user_id', 'created_at'],
        ],
        'order_items' => ['order_items_order_food_idx' => ['order_id', 'menu_item_id']],
        'order_charges' => ['order_charges_order_kind_idx' => ['order_id', 'kind', 'currency']],
    ];

    public function up(): void
    {
        foreach (self::INDEXES as $table => $indexes) {
            foreach ($indexes as $name => $columns) {
                if (!Schema::hasIndex($table, $name)) {
                    Schema::table($table, fn (Blueprint $t) => $t->index($columns, $name));
                }
            }
        }
    }

    public function down(): void
    {
        foreach (self::INDEXES as $table => $indexes) {
            foreach (array_keys($indexes) as $name) {
                if (Schema::hasIndex($table, $name)) {
                    Schema::table($table, fn (Blueprint $t) => $t->dropIndex($name));
                }
            }
        }
    }
};
