<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (!Schema::hasTable('event_compliance_acknowledgements')) {
            Schema::create('event_compliance_acknowledgements', function (Blueprint $table) {
                $table->uuid('id')->primary();
                $table->string('event_id', 64)->index();
                $table->unsignedBigInteger('user_id')->index();
                $table->string('policy_version', 32);
                $table->string('acknowledgement_context', 64)->default('event_creation');

                // Applicable Jurisdiction Snapshot
                $table->string('jurisdiction_country', 16)->default('US');
                $table->string('jurisdiction_state', 80)->nullable();
                $table->string('jurisdiction_county', 120)->nullable();
                $table->string('jurisdiction_municipality', 120)->nullable();

                // Food & Alcohol Arrangements Snapshot
                $table->boolean('supports_food_ordering')->default(true);
                $table->string('food_permit_status', 64)->nullable();
                $table->string('food_permit_number', 120)->nullable();
                $table->boolean('serves_alcohol')->default(false);
                $table->boolean('alcohol_age_verification_required')->default(false);
                $table->string('alcohol_permit_status', 64)->nullable();
                $table->string('alcohol_permit_number', 120)->nullable();

                // Structured Mandatory Event Declarations
                $table->json('food_safety_declarations');
                $table->json('alcohol_declarations');
                $table->json('general_legal_declarations');
                $table->boolean('all_declarations_accepted')->default(false);
                $table->string('arrangement_fingerprint', 64)->index();

                // Audit & Supersession Metadata
                $table->timestamp('accepted_at_utc');
                $table->string('ip_address', 45)->nullable();
                $table->string('user_agent', 512)->nullable();
                $table->timestamp('superseded_at_utc')->nullable();
                $table->string('superseded_reason', 500)->nullable();
                $table->timestamps();

                $table->index(['event_id', 'superseded_at_utc'], 'evt_comp_ack_event_active_idx');
            });
        }

        if (Schema::hasTable('events')) {
            Schema::table('events', function (Blueprint $table) {
                if (!Schema::hasColumn('events', 'compliance_jurisdiction_county')) {
                    $table->string('compliance_jurisdiction_county', 120)->nullable()->after('state_code');
                }
                if (!Schema::hasColumn('events', 'compliance_jurisdiction_municipality')) {
                    $table->string('compliance_jurisdiction_municipality', 120)->nullable()->after('compliance_jurisdiction_county');
                }
                if (!Schema::hasColumn('events', 'compliance_acknowledgement_id')) {
                    $table->uuid('compliance_acknowledgement_id')->nullable()->after('compliance_verified_at');
                }
                if (!Schema::hasColumn('events', 'compliance_reacknowledgement_required')) {
                    $table->boolean('compliance_reacknowledgement_required')->default(false)->after('compliance_acknowledgement_id');
                }
                if (!Schema::hasColumn('events', 'compliance_reacknowledgement_reason')) {
                    $table->string('compliance_reacknowledgement_reason', 500)->nullable()->after('compliance_reacknowledgement_required');
                }
                if (!Schema::hasColumn('events', 'compliance_arrangement_fingerprint')) {
                    $table->string('compliance_arrangement_fingerprint', 64)->nullable()->after('compliance_reacknowledgement_reason');
                }
            });
        }
    }

    public function down(): void
    {
        if (Schema::hasTable('events')) {
            Schema::table('events', function (Blueprint $table) {
                $drop = [];
                foreach ([
                    'compliance_jurisdiction_county',
                    'compliance_jurisdiction_municipality',
                    'compliance_acknowledgement_id',
                    'compliance_reacknowledgement_required',
                    'compliance_reacknowledgement_reason',
                    'compliance_arrangement_fingerprint',
                ] as $col) {
                    if (Schema::hasColumn('events', $col)) {
                        $drop[] = $col;
                    }
                }
                if ($drop !== []) {
                    $table->dropColumn($drop);
                }
            });
        }

        Schema::dropIfExists('event_compliance_acknowledgements');
    }
};

