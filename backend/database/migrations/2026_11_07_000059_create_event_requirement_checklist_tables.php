<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Jurisdiction-specific event requirements:
 * - how an event provides food and alcohol, and where they are prepared and served (on events);
 * - the requirement rules, exceptions and additional local requirements administrators configure per
 *   jurisdiction (compliance_event_rules);
 * - the evidence a host submits for a checklist item and its review (event_compliance_evidence).
 * Only adds tables and nullable columns.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('events', function (Blueprint $table) {
            $table->string('food_service_mode', 16)->nullable();      // none, sold, complimentary, both
            $table->string('alcohol_service_mode', 16)->nullable();   // none, sold, included, complimentary, byob
            $table->boolean('uses_licensed_caterer')->nullable();
            $table->boolean('uses_licensed_alcohol_vendor')->nullable();
            // Where food is prepared and alcohol served, when not at the event address.
            $table->boolean('food_prepared_elsewhere')->default(false);
            $table->string('food_prep_state_code', 2)->nullable();
            $table->string('food_prep_county', 120)->nullable();
            $table->string('food_prep_city', 120)->nullable();
            $table->boolean('alcohol_served_elsewhere')->default(false);
            $table->string('alcohol_service_state_code', 2)->nullable();
            $table->string('alcohol_service_county', 120)->nullable();
            $table->string('alcohol_service_city', 120)->nullable();
        });

        Schema::create('compliance_event_rules', function (Blueprint $table) {
            $table->id();
            $table->foreignId('jurisdiction_id')->constrained('compliance_jurisdictions')->cascadeOnDelete();
            $table->boolean('include_descendants')->default(false); // also applies to every county and city inside it
            // A standard topic (ComplianceRequirement::TOPICS) or "local" for an additional local requirement.
            $table->string('topic', 40);
            $table->string('title', 160)->nullable();                // for an additional local requirement
            $table->text('description')->nullable();
            $table->string('effect', 16);                           // required, not_required
            $table->string('location', 24);                         // event, food_preparation, alcohol_service
            $table->json('conditions')->nullable();                  // event_types, food_modes, alcohol_modes, caterer, alcohol_vendor
            $table->foreignId('requirement_id')->nullable()->constrained('compliance_requirements')->nullOnDelete();
            $table->string('source_url', 500);
            $table->string('source_title', 255)->nullable();
            $table->boolean('is_active')->default(true)->index();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('updated_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();

            $table->index(['jurisdiction_id', 'topic']);
        });

        Schema::create('event_compliance_evidence', function (Blueprint $table) {
            $table->id();
            $table->string('event_id', 64)->index();
            $table->string('item_key', 160);                        // location:topic-or-rule:jurisdiction
            $table->string('item_title', 255);
            $table->string('status', 24)->index();                  // submitted, pending_verification, verified, rejected
            $table->string('reference_number', 120)->nullable();
            $table->string('issuing_authority', 255)->nullable();
            $table->date('expires_on')->nullable();
            $table->text('note')->nullable();
            $table->string('document_path', 500)->nullable();       // private disk, never public
            $table->string('document_name', 255)->nullable();
            $table->string('document_mime', 100)->nullable();
            $table->foreignId('submitted_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('submitted_at')->nullable();
            $table->foreignId('reviewed_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('reviewed_at')->nullable();
            $table->text('review_comment')->nullable();
            $table->timestamps();

            $table->index(['event_id', 'item_key']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('event_compliance_evidence');
        Schema::dropIfExists('compliance_event_rules');
        Schema::table('events', function (Blueprint $table) {
            $table->dropColumn(['food_service_mode', 'alcohol_service_mode', 'uses_licensed_caterer', 'uses_licensed_alcohol_vendor',
                'food_prepared_elsewhere', 'food_prep_state_code', 'food_prep_county', 'food_prep_city',
                'alcohol_served_elsewhere', 'alcohol_service_state_code', 'alcohol_service_county', 'alcohol_service_city']);
        });
    }
};
