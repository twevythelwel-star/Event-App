<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('events', function (Blueprint $table) {
            $table->string('flyer_alt_text', 250)->nullable();
            $table->unsignedTinyInteger('flyer_position_x')->default(50);
            $table->unsignedTinyInteger('flyer_position_y')->default(50);
        });

        Schema::create('event_media', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->uuid('event_id');
            $table->string('media_type', 24);
            $table->string('original_path', 500);
            $table->string('thumbnail_path', 500)->nullable();
            $table->string('mime_type', 64);
            $table->unsignedInteger('size_bytes');
            $table->unsignedInteger('width');
            $table->unsignedInteger('height');
            $table->string('alt_text', 250);
            $table->unsignedTinyInteger('position_x')->default(50);
            $table->unsignedTinyInteger('position_y')->default(50);
            $table->unsignedInteger('sort_order')->default(0);
            $table->timestamps();
            $table->foreign('event_id')->references('id')->on('events')->cascadeOnDelete();
            $table->index(['event_id', 'media_type', 'sort_order']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('event_media');
        Schema::table('events', function (Blueprint $table) {
            $table->dropColumn(['flyer_alt_text', 'flyer_position_x', 'flyer_position_y']);
        });
    }
};