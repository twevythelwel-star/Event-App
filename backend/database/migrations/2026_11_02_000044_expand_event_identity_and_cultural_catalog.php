<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('events', function (Blueprint $table) {
            $table->string('summary', 500)->nullable();
            $table->json('event_types')->nullable();
            $table->string('event_format', 24)->default('in_person');
            $table->unsignedInteger('expected_attendance')->nullable();
            $table->string('age_restriction', 120)->nullable();
            $table->string('dress_code', 160)->nullable();
            $table->text('accessibility_information')->nullable();
            $table->json('languages')->nullable();
            $table->string('visibility', 24)->default('public');
            $table->boolean('is_featured')->default(false);
            $table->string('virtual_url', 2048)->nullable();
            $table->text('virtual_access_instructions')->nullable();
        });

        Schema::create('cultural_categories', function (Blueprint $table) {
            $table->string('id', 48)->primary();
            $table->string('name', 120);
            $table->unsignedInteger('sort_order')->default(0);
            $table->boolean('is_active')->default(true);
            $table->timestamps();
            $table->index(['is_active', 'sort_order']);
        });

        Schema::create('cultural_regions', function (Blueprint $table) {
            $table->string('id', 64)->primary();
            $table->string('cultural_category_id', 48);
            $table->string('name', 120);
            $table->unsignedInteger('sort_order')->default(0);
            $table->boolean('is_active')->default(true);
            $table->timestamps();
            $table->foreign('cultural_category_id')->references('id')->on('cultural_categories')->cascadeOnDelete();
            $table->index(['cultural_category_id', 'is_active', 'sort_order']);
        });

        Schema::create('countries', function (Blueprint $table) {
            $table->char('id', 2)->primary();
            $table->string('name', 120);
            $table->char('flag_emoji', 8)->nullable();
            $table->string('cuisine_image_url', 500)->nullable();
            $table->unsignedInteger('sort_order')->default(0);
            $table->boolean('is_active')->default(true);
            $table->timestamps();
            $table->index(['is_active', 'sort_order']);
        });

        Schema::create('cultural_region_countries', function (Blueprint $table) {
            $table->string('cultural_region_id', 64);
            $table->char('country_id', 2);
            $table->primary(['cultural_region_id', 'country_id']);
            $table->foreign('cultural_region_id')->references('id')->on('cultural_regions')->cascadeOnDelete();
            $table->foreign('country_id')->references('id')->on('countries')->cascadeOnDelete();
            $table->index('country_id');
        });

        Schema::create('cuisines', function (Blueprint $table) {
            $table->string('id', 80)->primary();
            $table->char('country_id', 2);
            $table->string('name', 160);
            $table->unsignedInteger('sort_order')->default(0);
            $table->boolean('is_active')->default(true);
            $table->timestamps();
            $table->foreign('country_id')->references('id')->on('countries')->cascadeOnDelete();
            $table->index(['country_id', 'is_active', 'sort_order']);
        });

        Schema::create('dishes', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->string('cuisine_id', 80);
            $table->string('name', 160);
            $table->json('alternative_spellings')->nullable();
            $table->boolean('is_fusion')->default(false);
            $table->string('dietary_type', 24)->default('any');
            $table->unsignedInteger('sort_order')->default(0);
            $table->boolean('is_active')->default(true);
            $table->timestamps();
            $table->foreign('cuisine_id')->references('id')->on('cuisines')->cascadeOnDelete();
            $table->index(['cuisine_id', 'is_active', 'sort_order']);
        });

        Schema::create('event_cultural_categories', function (Blueprint $table) {
            $table->uuid('event_id');
            $table->string('cultural_category_id', 48);
            $table->primary(['event_id', 'cultural_category_id']);
            $table->foreign('event_id')->references('id')->on('events')->cascadeOnDelete();
            $table->foreign('cultural_category_id')->references('id')->on('cultural_categories')->cascadeOnDelete();
        });

        Schema::create('event_countries', function (Blueprint $table) {
            $table->uuid('event_id');
            $table->char('country_id', 2);
            $table->primary(['event_id', 'country_id']);
            $table->foreign('event_id')->references('id')->on('events')->cascadeOnDelete();
            $table->foreign('country_id')->references('id')->on('countries')->cascadeOnDelete();
        });

        Schema::create('event_dishes', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->uuid('event_id');
            $table->uuid('dish_id')->nullable();
            $table->char('country_id', 2)->nullable();
            $table->string('custom_name', 160)->nullable();
            $table->json('alternative_spellings')->nullable();
            $table->boolean('is_fusion')->default(false);
            $table->string('dietary_type', 24)->default('any');
            $table->timestamps();
            $table->foreign('event_id')->references('id')->on('events')->cascadeOnDelete();
            $table->foreign('dish_id')->references('id')->on('dishes')->nullOnDelete();
            $table->foreign('country_id')->references('id')->on('countries')->nullOnDelete();
            $table->unique(['event_id', 'dish_id']);
            $table->index(['event_id', 'custom_name']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('event_dishes');
        Schema::dropIfExists('event_countries');
        Schema::dropIfExists('event_cultural_categories');
        Schema::dropIfExists('dishes');
        Schema::dropIfExists('cuisines');
        Schema::dropIfExists('cultural_region_countries');
        Schema::dropIfExists('countries');
        Schema::dropIfExists('cultural_regions');
        Schema::dropIfExists('cultural_categories');

        Schema::table('events', function (Blueprint $table) {
            $table->dropColumn([
                'summary', 'event_types', 'event_format', 'expected_attendance', 'age_restriction',
                'dress_code', 'accessibility_information', 'languages', 'visibility', 'is_featured',
                'virtual_url', 'virtual_access_instructions',
            ]);
        });
    }
};
