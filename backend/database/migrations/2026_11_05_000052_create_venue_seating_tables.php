<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * The venue designer and seat selection, additive and forward-only:
 * - venue_layouts: one per event; whether attendees choose seats;
 * - venue_areas: what is drawn on the plan (general admission, VIP, tables, rows of seats, food stations, vendor
 *   booths, restricted areas, stages), its position and size in plan units, and the ticket type it sells;
 * - venue_seats: the individual seats of tables and seat rows. A seat belongs to an order while that order is live;
 *   (event_id, label) is unique, so the same seat can never exist twice.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('venue_layouts', function (Blueprint $table) {
            $table->uuid('event_id')->primary();
            $table->boolean('seat_selection_enabled')->default(false);
            $table->unsignedSmallInteger('width')->default(1000);
            $table->unsignedSmallInteger('height')->default(640);
            $table->string('notes', 500)->nullable();
            $table->timestamps();
            $table->foreign('event_id')->references('id')->on('events')->cascadeOnDelete();
        });

        Schema::create('venue_areas', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->uuid('event_id');
            $table->string('kind', 24);
            $table->string('label', 60);
            $table->unsignedSmallInteger('x');
            $table->unsignedSmallInteger('y');
            $table->unsignedSmallInteger('width');
            $table->unsignedSmallInteger('height');
            $table->string('ticket_tier_id', 64)->nullable();
            $table->unsignedSmallInteger('capacity')->nullable();
            $table->unsignedTinyInteger('rows')->nullable();
            $table->unsignedTinyInteger('seats_per_row')->nullable();
            $table->unsignedTinyInteger('seats_per_table')->nullable();
            $table->boolean('is_accessible')->default(false);
            $table->string('notes', 200)->nullable();
            $table->unsignedSmallInteger('sort_order')->default(0);
            $table->timestamps();
            $table->foreign('event_id')->references('id')->on('events')->cascadeOnDelete();
            $table->index(['event_id', 'sort_order']);
        });

        Schema::create('venue_seats', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->uuid('event_id');
            $table->uuid('area_id');
            $table->string('label', 24);
            $table->string('row_label', 8)->nullable();
            $table->unsignedSmallInteger('number');
            $table->unsignedSmallInteger('x');
            $table->unsignedSmallInteger('y');
            $table->boolean('is_blocked')->default(false);
            $table->boolean('is_accessible')->default(false);
            $table->uuid('order_id')->nullable();
            $table->timestamp('claimed_at')->nullable();
            $table->timestamps();
            $table->foreign('event_id')->references('id')->on('events')->cascadeOnDelete();
            $table->foreign('area_id')->references('id')->on('venue_areas')->cascadeOnDelete();
            $table->unique(['event_id', 'label']);
            $table->index(['event_id', 'order_id']);
            $table->index('area_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('venue_seats');
        Schema::dropIfExists('venue_areas');
        Schema::dropIfExists('venue_layouts');
    }
};
