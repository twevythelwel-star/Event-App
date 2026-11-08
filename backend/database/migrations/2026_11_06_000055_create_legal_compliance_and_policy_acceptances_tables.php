<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (!Schema::hasTable('policy_acceptances')) {
            Schema::create('policy_acceptances', function (Blueprint $table) {
                $table->id();
                $table->unsignedBigInteger('user_id')->index();
                $table->string('policy_name', 120)->index();
                $table->string('policy_version', 40)->default('2026.11-v1');
                $table->string('acceptance_status', 32)->default('accepted');
                $table->timestamp('accepted_at_utc');
                $table->string('ip_address', 64)->nullable();
                $table->string('user_agent', 512)->nullable();
                $table->string('context', 64)->index(); // account_registration, host_application, event_creation, event_publishing
                $table->string('context_entity_id', 120)->nullable()->index();
                $table->json('metadata')->nullable();
                $table->timestamps();

                $table->index(['user_id', 'policy_name', 'context'], 'idx_user_policy_context');
            });
        }

        if (Schema::hasTable('host_applications')) {
            Schema::table('host_applications', function (Blueprint $table) {
                if (!Schema::hasColumn('host_applications', 'legal_full_name')) {
                    $table->string('legal_full_name', 255)->nullable();
                }
                if (!Schema::hasColumn('host_applications', 'business_entity_type')) {
                    $table->string('business_entity_type', 64)->nullable();
                }
                if (!Schema::hasColumn('host_applications', 'government_id_type')) {
                    $table->string('government_id_type', 64)->nullable();
                }
                if (!Schema::hasColumn('host_applications', 'government_id_last4')) {
                    $table->string('government_id_last4', 16)->nullable();
                }
                if (!Schema::hasColumn('host_applications', 'identity_verification_status')) {
                    $table->string('identity_verification_status', 40)->default('pending_verification');
                }
                if (!Schema::hasColumn('host_applications', 'food_handler_permit_number')) {
                    $table->string('food_handler_permit_number', 120)->nullable();
                }
                if (!Schema::hasColumn('host_applications', 'alcohol_license_number')) {
                    $table->string('alcohol_license_number', 120)->nullable();
                }
                if (!Schema::hasColumn('host_applications', 'compliance_acknowledged_at')) {
                    $table->timestamp('compliance_acknowledged_at')->nullable();
                }
            });
        }

        if (Schema::hasTable('events')) {
            Schema::table('events', function (Blueprint $table) {
                if (!Schema::hasColumn('events', 'serves_alcohol')) {
                    $table->boolean('serves_alcohol')->default(false);
                }
                if (!Schema::hasColumn('events', 'alcohol_age_verification_required')) {
                    $table->boolean('alcohol_age_verification_required')->default(false);
                }
                if (!Schema::hasColumn('events', 'food_permit_status')) {
                    $table->string('food_permit_status', 64)->nullable();
                }
                if (!Schema::hasColumn('events', 'food_permit_number')) {
                    $table->string('food_permit_number', 120)->nullable();
                }
                if (!Schema::hasColumn('events', 'alcohol_permit_status')) {
                    $table->string('alcohol_permit_status', 64)->nullable();
                }
                if (!Schema::hasColumn('events', 'alcohol_permit_number')) {
                    $table->string('alcohol_permit_number', 120)->nullable();
                }
                if (!Schema::hasColumn('events', 'allergen_disclosure_confirmed')) {
                    $table->boolean('allergen_disclosure_confirmed')->default(false);
                }
                if (!Schema::hasColumn('events', 'temperature_and_sanitation_confirmed')) {
                    $table->boolean('temperature_and_sanitation_confirmed')->default(false);
                }
                if (!Schema::hasColumn('events', 'compliance_verified_at')) {
                    $table->timestamp('compliance_verified_at')->nullable();
                }
            });
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('policy_acceptances');
    }
};

