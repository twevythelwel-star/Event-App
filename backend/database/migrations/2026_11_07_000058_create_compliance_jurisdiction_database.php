<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * The US state and local compliance database: a jurisdiction hierarchy (country → state → county →
 * city/municipality), the authorities that regulate food and alcohol in each, the requirement records an
 * administrator researches and approves, and the history of every review step. Only adds tables.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('compliance_jurisdictions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('parent_id')->nullable()->constrained('compliance_jurisdictions')->nullOnDelete();
            $table->string('type', 16)->index(); // country, state, county, municipality
            $table->string('name', 160);
            // US, a state code (FL), a 5-digit county FIPS code or a 7-digit place FIPS code (state + place).
            $table->string('code', 16);
            $table->string('state_code', 2)->nullable()->index();
            $table->string('census_class', 8)->nullable();   // Census class code (H1, C1, ...)
            $table->string('data_source', 64)->default('manual');
            $table->timestamps();

            $table->unique(['type', 'code']);
            $table->index(['parent_id', 'name']);
        });

        $review = function (Blueprint $table) {
            // verification_required, draft, pending_review, changes_requested, verified, review_due
            $table->string('status', 24)->default('verification_required')->index();
            $table->string('source_url', 500)->nullable();
            $table->string('source_title', 255)->nullable();
            $table->boolean('source_confirmed_official')->default(false);
            $table->date('effective_date')->nullable();
            $table->date('last_verified_date')->nullable();
            $table->date('next_review_date')->nullable()->index();
            $table->text('admin_notes')->nullable();
            $table->text('research_notes')->nullable();
            $table->foreignId('submitted_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('submitted_at')->nullable();
            $table->foreignId('approved_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('approved_at')->nullable();
            $table->timestamps();
        };

        Schema::create('compliance_authorities', function (Blueprint $table) use ($review) {
            $table->id();
            $table->foreignId('jurisdiction_id')->constrained('compliance_jurisdictions')->cascadeOnDelete();
            $table->string('area', 16)->index();     // food, alcohol, agriculture, revenue, general
            $table->string('role', 255);             // what it does, e.g. "Retail food oversight (restaurants)"
            $table->string('name', 255);
            $table->string('website_url', 500)->nullable();
            $table->string('legislation_url', 500)->nullable();
            $table->string('legislation_title', 255)->nullable();
            $review($table);
        });

        Schema::create('compliance_requirements', function (Blueprint $table) use ($review) {
            $table->id();
            $table->foreignId('jurisdiction_id')->constrained('compliance_jurisdictions')->cascadeOnDelete();
            $table->foreignId('authority_id')->nullable()->constrained('compliance_authorities')->nullOnDelete();
            $table->string('topic', 40)->index();
            $table->text('summary')->nullable();
            $table->string('value', 120)->nullable();   // a short fact where one applies, e.g. the minimum age
            $table->text('conditions')->nullable();
            $table->text('required_documents')->nullable();
            $table->string('legislation_citation', 255)->nullable();
            $review($table);

            $table->unique(['jurisdiction_id', 'topic']);
        });

        Schema::create('compliance_record_reviews', function (Blueprint $table) {
            $table->id();
            $table->string('record_type', 16);    // authority, requirement
            $table->unsignedBigInteger('record_id');
            $table->string('action', 32);         // created, edited, submitted, approved, changes_requested, reset, review_due
            $table->string('from_status', 24)->nullable();
            $table->string('to_status', 24)->nullable();
            $table->foreignId('user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->text('comment')->nullable();
            $table->json('snapshot')->nullable();
            $table->timestamp('created_at')->useCurrent();

            $table->index(['record_type', 'record_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('compliance_record_reviews');
        Schema::dropIfExists('compliance_requirements');
        Schema::dropIfExists('compliance_authorities');
        Schema::dropIfExists('compliance_jurisdictions');
    }
};
