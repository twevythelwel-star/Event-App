<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Image management, forward-only and additive:
 * - each uploaded image keeps its sanitized original and records its responsive variants (sizes × formats) as
 *   storage references in a JSON map; no image bytes are stored in the database;
 * - menu photos get their own alt text and variants, like flyers and gallery photos already have;
 * - every host-submitted image carries a moderation status, so an administrator can hide one without deleting it.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('events', function (Blueprint $table) {
            $table->json('flyer_variants')->nullable();
            $table->string('flyer_moderation_status', 16)->default('approved');
        });

        Schema::table('menu_items', function (Blueprint $table) {
            $table->json('image_variants')->nullable();
            $table->string('image_alt_text', 250)->nullable();
            $table->unsignedSmallInteger('image_width')->nullable();
            $table->unsignedSmallInteger('image_height')->nullable();
            $table->string('image_moderation_status', 16)->default('approved');
        });

        Schema::table('event_media', function (Blueprint $table) {
            $table->json('variants')->nullable();
            $table->string('moderation_status', 16)->default('approved');
            $table->uuid('moderated_by')->nullable();
            $table->timestamp('moderated_at')->nullable();
            $table->string('moderation_note', 250)->nullable();
            $table->uuid('uploaded_by')->nullable();
        });
    }

    public function down(): void
    {
        Schema::table('event_media', function (Blueprint $table) {
            $table->dropColumn(['variants', 'moderation_status', 'moderated_by', 'moderated_at', 'moderation_note', 'uploaded_by']);
        });
        Schema::table('menu_items', function (Blueprint $table) {
            $table->dropColumn(['image_variants', 'image_alt_text', 'image_width', 'image_height', 'image_moderation_status']);
        });
        Schema::table('events', function (Blueprint $table) {
            $table->dropColumn(['flyer_variants', 'flyer_moderation_status']);
        });
    }
};
