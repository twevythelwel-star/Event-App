<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * The event's itinerary, additive and forward-only:
 * - stages: the places activities happen (a main stage, a food court, a tent);
 * - performers: the DJs, performers, bands, speakers and hosts on the bill, with a profile and an optional photo
 *   (a reference to a stored upload, never image bytes);
 * - activities: what happens when and where, linked to a stage and to any number of performers.
 * Times are stored in UTC; pages show them in the event's own timezone.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('event_stages', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->uuid('event_id');
            $table->string('name', 80);
            $table->string('description', 300)->nullable();
            $table->unsignedSmallInteger('sort_order')->default(0);
            $table->timestamps();
            $table->foreign('event_id')->references('id')->on('events')->cascadeOnDelete();
            $table->unique(['event_id', 'name']);
        });

        Schema::create('event_performers', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->uuid('event_id');
            $table->string('name', 120);
            $table->string('role', 20); // dj, performer, band, speaker, host
            $table->string('headline', 160)->nullable();
            $table->text('bio')->nullable();
            $table->json('links')->nullable();
            $table->string('photo_path', 500)->nullable();
            $table->json('photo_variants')->nullable();
            $table->string('photo_alt_text', 250)->nullable();
            $table->unsignedSmallInteger('sort_order')->default(0);
            $table->timestamps();
            $table->foreign('event_id')->references('id')->on('events')->cascadeOnDelete();
            $table->index(['event_id', 'sort_order']);
        });

        Schema::create('event_activities', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->uuid('event_id');
            $table->uuid('stage_id')->nullable();
            $table->string('title', 150);
            $table->string('kind', 20); // performance, dj_set, talk, food_service, activity, ceremony, break
            $table->text('description')->nullable();
            $table->timestamp('starts_at');
            $table->timestamp('ends_at');
            $table->boolean('is_public')->default(true);
            $table->timestamps();
            $table->foreign('event_id')->references('id')->on('events')->cascadeOnDelete();
            $table->foreign('stage_id')->references('id')->on('event_stages')->nullOnDelete();
            $table->index(['event_id', 'starts_at']);
        });

        Schema::create('event_activity_performer', function (Blueprint $table) {
            $table->uuid('activity_id');
            $table->uuid('performer_id');
            $table->primary(['activity_id', 'performer_id']);
            $table->foreign('activity_id')->references('id')->on('event_activities')->cascadeOnDelete();
            $table->foreign('performer_id')->references('id')->on('event_performers')->cascadeOnDelete();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('event_activity_performer');
        Schema::dropIfExists('event_activities');
        Schema::dropIfExists('event_performers');
        Schema::dropIfExists('event_stages');
    }
};
