<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('event_media', function (Blueprint $table) {
            $table->unsignedSmallInteger('crop_x')->nullable()->after('position_y');
            $table->unsignedSmallInteger('crop_y')->nullable()->after('crop_x');
            $table->unsignedSmallInteger('crop_width')->nullable()->after('crop_y');
            $table->unsignedSmallInteger('crop_height')->nullable()->after('crop_width');
            $table->string('small_thumbnail_path', 500)->nullable()->after('thumbnail_path');
            $table->string('medium_thumbnail_path', 500)->nullable()->after('small_thumbnail_path');
            $table->string('large_thumbnail_path', 500)->nullable()->after('medium_thumbnail_path');
        });
    }

    public function down(): void
    {
        Schema::table('event_media', function (Blueprint $table) {
            $table->dropColumn(['crop_x', 'crop_y', 'crop_width', 'crop_height']);
            $table->dropColumn(['small_thumbnail_path', 'medium_thumbnail_path', 'large_thumbnail_path']);
        });
    }
};
