<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('us_states', function (Blueprint $table) {
            $table->char('code', 2)->primary();
            $table->string('name', 80)->unique();
            $table->string('default_timezone', 64);
            $table->boolean('is_state_or_dc')->default(true);
            $table->boolean('is_enabled')->default(true);
            $table->timestamps();
        });

        Schema::create('target_markets', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreign('state_code')->references('code')->on('us_states')->restrictOnDelete();
            $table->char('state_code', 2);
            $table->string('city', 120);
            $table->string('slug', 160)->unique();
            $table->string('default_timezone', 64);
            $table->json('zip_codes')->nullable();
            $table->json('metadata')->nullable();
            $table->unsignedSmallInteger('discovery_priority')->default(100);
            $table->boolean('is_featured')->default(false);
            $table->boolean('is_enabled')->default(true);
            $table->timestamps();
            $table->unique(['city', 'state_code']);
            $table->index(['state_code', 'is_enabled', 'discovery_priority']);
        });

        Schema::table('events', function (Blueprint $table) {
            $table->string('street_address', 255)->nullable();
            $table->string('venue_building_name', 180)->nullable();
            $table->string('city', 120)->nullable()->index();
            $table->char('state_code', 2)->nullable()->index();
            $table->string('zip_code', 10)->nullable()->index();
            $table->char('country_code', 2)->nullable();
            $table->decimal('latitude', 10, 7)->nullable();
            $table->decimal('longitude', 10, 7)->nullable();
            $table->foreign('state_code')->references('code')->on('us_states')->nullOnDelete();
            $table->foreignUuid('target_market_id')->nullable()->after('state_code')->constrained('target_markets')->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('events', function (Blueprint $table) {
            $table->dropForeign(['target_market_id']);
            $table->dropForeign(['state_code']);
            $table->dropColumn([
                'street_address', 'venue_building_name', 'city', 'state_code',
                'target_market_id', 'zip_code', 'country_code', 'latitude', 'longitude',
            ]);
        });
        Schema::dropIfExists('target_markets');
        Schema::dropIfExists('us_states');
    }
};
