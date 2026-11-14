<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Event scheduling, venue details, verified map locations, the food service plan, the menu and package builder,
 * and item-level food fulfillment.
 *
 * - events: an end date and exact UTC start/end instants (derived from the local date and times, so events past
 *   midnight and over several days are unambiguous), doors-open, setup and food service times, ticket sales and
 *   early-bird windows, venue type, instructions and parking, where a map pin came from, and the food service plan.
 * - menu_items: ingredients, per-order limit, preparation time, availability window, featured flag, whether the
 *   photo is illustrative, the photo's focal point, and packages (item_type "package" with their components).
 * - order_items: what kind of line it is, the package line a component belongs to, and preparation status, so each
 *   item can be prepared and handed over on its own.
 * - orders: how and when the food is collected.
 *
 * Additive only: every column is nullable or has a default, so existing events, menus and orders are unchanged.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('events', function (Blueprint $table) {
            $table->date('end_date_iso')->nullable()->after('event_date_iso');
            $table->dateTime('starts_at')->nullable()->after('end_time');
            $table->dateTime('ends_at')->nullable()->after('starts_at');
            $table->string('doors_open_time', 5)->nullable()->after('ends_at');
            $table->string('setup_time', 5)->nullable()->after('doors_open_time');
            $table->string('food_service_start_time', 5)->nullable()->after('setup_time');
            $table->string('food_service_end_time', 5)->nullable()->after('food_service_start_time');
            $table->dateTime('ticket_sales_start_at')->nullable();
            $table->dateTime('ticket_sales_end_at')->nullable();
            $table->dateTime('early_bird_ends_at')->nullable();

            $table->string('venue_type', 40)->nullable()->after('venue_name');
            $table->text('venue_instructions')->nullable();
            $table->text('parking_information')->nullable();
            $table->string('location_source', 40)->nullable();
            $table->string('location_place_id', 255)->nullable();
            $table->dateTime('location_verified_at')->nullable();

            $table->string('food_ordering_mode', 20)->default('preorder');
            $table->string('food_fulfillment', 30)->default('pickup');
            $table->text('food_delivery_instructions')->nullable();
            $table->unsignedInteger('food_service_fee_minor')->default(0);
            $table->json('food_pickup_windows')->nullable();
            $table->unsignedInteger('food_max_orders')->nullable();

            $table->index(['status', 'starts_at'], 'events_status_starts_idx');
        });

        Schema::table('menu_items', function (Blueprint $table) {
            $table->string('item_type', 20)->default('item')->after('category');
            $table->text('ingredients')->nullable();
            $table->text('guest_instructions')->nullable();
            $table->unsignedSmallInteger('max_per_order')->nullable();
            $table->unsignedSmallInteger('prep_minutes')->nullable();
            $table->dateTime('available_from')->nullable();
            $table->dateTime('available_until')->nullable();
            $table->boolean('is_featured')->default(false);
            $table->boolean('image_is_illustrative')->default(false);
            $table->string('image_position', 20)->nullable();
            $table->json('package_config')->nullable();

            $table->index(['event_id', 'item_type', 'is_active'], 'menu_items_event_type_idx');
        });

        Schema::table('order_items', function (Blueprint $table) {
            $table->string('line_type', 20)->default('item');
            $table->uuid('package_line_id')->nullable();
            $table->string('preparation_status', 20)->default('pending');

            $table->index(['order_id', 'package_line_id'], 'order_items_order_package_idx');
        });

        Schema::table('orders', function (Blueprint $table) {
            $table->string('food_fulfillment_method', 20)->nullable();
            $table->string('pickup_window', 11)->nullable();
            $table->string('delivery_location', 160)->nullable();

            $table->index(['event_id', 'pickup_window'], 'orders_event_pickup_idx');
        });
    }

    public function down(): void
    {
        foreach (['orders' => 'orders_event_pickup_idx', 'order_items' => 'order_items_order_package_idx', 'menu_items' => 'menu_items_event_type_idx'] as $table => $index) {
            \App\Support\ForeignKeyIndexes::keepFor($table, $index);
        }
        Schema::table('orders', function (Blueprint $table) {
            $table->dropIndex('orders_event_pickup_idx');
            $table->dropColumn(['food_fulfillment_method', 'pickup_window', 'delivery_location']);
        });
        Schema::table('order_items', function (Blueprint $table) {
            $table->dropIndex('order_items_order_package_idx');
            $table->dropColumn(['line_type', 'package_line_id', 'preparation_status']);
        });
        Schema::table('menu_items', function (Blueprint $table) {
            $table->dropIndex('menu_items_event_type_idx');
            $table->dropColumn(['item_type', 'ingredients', 'guest_instructions', 'max_per_order', 'prep_minutes',
                'available_from', 'available_until', 'is_featured', 'image_is_illustrative', 'image_position', 'package_config']);
        });
        Schema::table('events', function (Blueprint $table) {
            $table->dropIndex('events_status_starts_idx');
            $table->dropColumn(['end_date_iso', 'starts_at', 'ends_at', 'doors_open_time', 'setup_time',
                'food_service_start_time', 'food_service_end_time', 'ticket_sales_start_at', 'ticket_sales_end_at',
                'early_bird_ends_at', 'venue_type', 'venue_instructions', 'parking_information', 'location_source',
                'location_place_id', 'location_verified_at', 'food_ordering_mode', 'food_fulfillment',
                'food_delivery_instructions', 'food_service_fee_minor', 'food_pickup_windows', 'food_max_orders']);
        });
    }
};
