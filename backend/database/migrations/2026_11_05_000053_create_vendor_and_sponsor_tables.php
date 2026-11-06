<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Vendors, exhibitors and sponsors, additive and forward-only:
 * - vendor_offers: the booth types a host sells (price in minor units, how many, optionally a booth on the plan);
 * - vendor_applications: a business applying to take part, the host's decision, the booth and fee agreed, and
 *   whether the fee is paid (recorded by the host; no card data is stored);
 * - sponsorship_packages: Gold, Silver, Bronze (or partner) packages with their benefits and price;
 * - event_sponsors: sponsors shown on the event page, generated flyer and tickets, with logo references (never bytes).
 * Events gain the vendor application window.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('events', function (Blueprint $table) {
            $table->boolean('vendor_applications_open')->default(false);
            $table->timestamp('vendor_applications_close_at')->nullable();
            $table->text('vendor_terms')->nullable();
        });

        Schema::create('vendor_offers', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->uuid('event_id');
            $table->string('name', 120);
            $table->string('category', 20)->nullable(); // limits the offer to one kind of vendor, or null for any
            $table->text('description')->nullable();
            $table->unsignedBigInteger('price_minor')->default(0);
            $table->unsignedSmallInteger('quantity')->nullable();
            $table->uuid('venue_area_id')->nullable();
            $table->boolean('is_active')->default(true);
            $table->unsignedSmallInteger('sort_order')->default(0);
            $table->timestamps();
            $table->foreign('event_id')->references('id')->on('events')->cascadeOnDelete();
            $table->foreign('venue_area_id')->references('id')->on('venue_areas')->nullOnDelete();
        });

        Schema::create('vendor_applications', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->uuid('event_id');
            $table->unsignedBigInteger('user_id');
            $table->uuid('offer_id')->nullable();
            $table->string('category', 20); // food, merchandise, craft, sponsor, exhibitor
            $table->string('business_name', 150);
            $table->string('contact_name', 120);
            $table->string('email', 190);
            $table->string('phone', 40);
            $table->text('description');
            $table->string('website', 300)->nullable();
            $table->text('needs')->nullable();
            $table->boolean('show_publicly')->default(true);
            $table->string('status', 16)->default('pending'); // pending, approved, declined, withdrawn
            $table->text('decision_note')->nullable();
            $table->unsignedBigInteger('decided_by')->nullable();
            $table->timestamp('decided_at')->nullable();
            $table->string('booth_label', 60)->nullable();
            $table->uuid('venue_area_id')->nullable();
            $table->unsignedBigInteger('fee_minor')->nullable();
            $table->string('fee_status', 12)->default('not_due'); // not_due, unpaid, paid, waived
            $table->timestamps();
            $table->foreign('event_id')->references('id')->on('events')->cascadeOnDelete();
            $table->foreign('offer_id')->references('id')->on('vendor_offers')->nullOnDelete();
            $table->foreign('venue_area_id')->references('id')->on('venue_areas')->nullOnDelete();
            $table->index(['event_id', 'status']);
            $table->index('user_id');
        });

        Schema::create('sponsorship_packages', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->uuid('event_id');
            $table->string('level', 12); // gold, silver, bronze, partner
            $table->string('name', 120);
            $table->text('benefits')->nullable();
            $table->unsignedBigInteger('price_minor')->default(0);
            $table->unsignedSmallInteger('quantity')->nullable();
            $table->timestamps();
            $table->foreign('event_id')->references('id')->on('events')->cascadeOnDelete();
        });

        Schema::create('event_sponsors', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->uuid('event_id');
            $table->uuid('package_id')->nullable();
            $table->uuid('application_id')->nullable();
            $table->string('name', 150);
            $table->string('level', 12);
            $table->text('description')->nullable();
            $table->string('website', 300)->nullable();
            $table->string('logo_path', 500)->nullable();
            $table->json('logo_variants')->nullable();
            $table->string('logo_alt_text', 250)->nullable();
            $table->boolean('show_on_page')->default(true);
            $table->boolean('show_on_flyer')->default(false);
            $table->boolean('show_on_ticket')->default(false);
            $table->unsignedSmallInteger('sort_order')->default(0);
            $table->timestamps();
            $table->foreign('event_id')->references('id')->on('events')->cascadeOnDelete();
            $table->foreign('package_id')->references('id')->on('sponsorship_packages')->nullOnDelete();
            $table->foreign('application_id')->references('id')->on('vendor_applications')->nullOnDelete();
            $table->index(['event_id', 'level', 'sort_order']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('event_sponsors');
        Schema::dropIfExists('sponsorship_packages');
        Schema::dropIfExists('vendor_applications');
        Schema::dropIfExists('vendor_offers');
        Schema::table('events', function (Blueprint $table) {
            $table->dropColumn(['vendor_applications_open', 'vendor_applications_close_at', 'vendor_terms']);
        });
    }
};
