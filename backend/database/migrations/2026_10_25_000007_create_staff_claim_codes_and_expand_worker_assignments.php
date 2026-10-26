<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        // 1. Staff Claim Codes table for privacy-preserving account discovery
        if (!Schema::hasTable('staff_claim_codes')) {
            Schema::create('staff_claim_codes', function (Blueprint $table) {
                $table->id();
                $table->foreignId('user_id')->constrained('users')->cascadeOnDelete();
                $table->string('code_hash', 64)->unique();
                $table->string('status', 32)->default('active'); // active, redeemed, revoked, expired
                $table->timestamp('expires_at');
                $table->timestamp('redeemed_at')->nullable();
                $table->foreignId('redeemed_by_host_id')->nullable()->constrained('users')->nullOnDelete();
                $table->foreignUuid('assigned_event_id')->nullable()->constrained('events')->nullOnDelete();
                $table->timestamp('revoked_at')->nullable();
                $table->string('ip_address', 45)->nullable();
                $table->text('user_agent')->nullable();
                $table->timestamps();

                $table->index(['user_id', 'status'], 'idx_user_claim_status');
                $table->index(['expires_at', 'status'], 'idx_expires_status');
            });
        }

        // 2. Expand event_worker_assignments with lifecycle and timezone columns if missing
        if (Schema::hasTable('event_worker_assignments')) {
            Schema::table('event_worker_assignments', function (Blueprint $table) {
                if (!Schema::hasColumn('event_worker_assignments', 'status')) {
                    $table->string('status', 32)->default('active')->after('custom_permissions');
                }
                if (!Schema::hasColumn('event_worker_assignments', 'starts_at')) {
                    $table->timestamp('starts_at')->nullable()->after('status');
                }
                if (!Schema::hasColumn('event_worker_assignments', 'expires_at')) {
                    $table->timestamp('expires_at')->nullable()->after('starts_at');
                }
                if (!Schema::hasColumn('event_worker_assignments', 'timezone')) {
                    $table->string('timezone', 64)->default('America/Jamaica')->after('expires_at');
                }
                if (!Schema::hasColumn('event_worker_assignments', 'accepted_at')) {
                    $table->timestamp('accepted_at')->nullable()->after('timezone');
                }
                if (!Schema::hasColumn('event_worker_assignments', 'declined_at')) {
                    $table->timestamp('declined_at')->nullable()->after('accepted_at');
                }
                if (!Schema::hasColumn('event_worker_assignments', 'decline_reason')) {
                    $table->string('decline_reason', 255)->nullable()->after('declined_at');
                }
                if (!Schema::hasColumn('event_worker_assignments', 'revoked_at')) {
                    $table->timestamp('revoked_at')->nullable()->after('decline_reason');
                }
                if (!Schema::hasColumn('event_worker_assignments', 'revoked_by')) {
                    $table->foreignId('revoked_by')->nullable()->constrained('users')->nullOnDelete()->after('revoked_at');
                }
                if (!Schema::hasColumn('event_worker_assignments', 'revocation_reason')) {
                    $table->string('revocation_reason', 255)->nullable()->after('revoked_by');
                }
            });
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('staff_claim_codes');
    }
};
