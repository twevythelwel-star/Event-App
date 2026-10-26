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
        // 1. Add split name, terms agreement, and language to users table
        if (Schema::hasTable('users')) {
            Schema::table('users', function (Blueprint $table) {
                if (!Schema::hasColumn('users', 'first_name')) {
                    $table->string('first_name', 100)->nullable()->after('name');
                }
                if (!Schema::hasColumn('users', 'last_name')) {
                    $table->string('last_name', 100)->nullable()->after('first_name');
                }
                if (!Schema::hasColumn('users', 'agreed_to_terms_at')) {
                    $table->timestamp('agreed_to_terms_at')->nullable()->after('phone_verified_at');
                }
                if (!Schema::hasColumn('users', 'preferred_language')) {
                    $table->string('preferred_language', 10)->default('en')->after('preferred_display_currency');
                }
            });
        }

        // 2. Create password_reset_tokens table
        if (!Schema::hasTable('password_reset_tokens')) {
            Schema::create('password_reset_tokens', function (Blueprint $table) {
                $table->string('email')->primary();
                $table->string('token'); // SHA-256 hash of token
                $table->timestamp('created_at')->nullable();
                $table->index('created_at');
            });
        }

        // 3. Enhance event_worker_assignments with full invitation lifecycle
        if (Schema::hasTable('event_worker_assignments')) {
            Schema::table('event_worker_assignments', function (Blueprint $table) {
                if (!Schema::hasColumn('event_worker_assignments', 'status')) {
                    $table->string('status', 24)->default('active')->after('worker_role'); // invited, active, suspended, revoked, expired
                }
                if (!Schema::hasColumn('event_worker_assignments', 'invite_email')) {
                    $table->string('invite_email')->nullable()->after('status');
                }
                if (!Schema::hasColumn('event_worker_assignments', 'invitation_token_hash')) {
                    $table->string('invitation_token_hash', 64)->nullable()->index()->after('invite_email');
                }
                if (!Schema::hasColumn('event_worker_assignments', 'invitation_sent_at')) {
                    $table->timestamp('invitation_sent_at')->nullable()->after('invitation_token_hash');
                }
                if (!Schema::hasColumn('event_worker_assignments', 'invitation_expires_at')) {
                    $table->timestamp('invitation_expires_at')->nullable()->after('invitation_sent_at');
                }
                if (!Schema::hasColumn('event_worker_assignments', 'invitation_accepted_at')) {
                    $table->timestamp('invitation_accepted_at')->nullable()->after('invitation_expires_at');
                }
                if (!Schema::hasColumn('event_worker_assignments', 'revoked_at')) {
                    $table->timestamp('revoked_at')->nullable()->after('invitation_accepted_at');
                }
                if (!Schema::hasColumn('event_worker_assignments', 'revoked_by')) {
                    $table->foreignId('revoked_by')->nullable()->constrained('users')->nullOnDelete()->after('revoked_at');
                }
                if (!Schema::hasColumn('event_worker_assignments', 'revocation_reason')) {
                    $table->string('revocation_reason')->nullable()->after('revoked_by');
                }
                if (!Schema::hasColumn('event_worker_assignments', 'starts_at')) {
                    $table->timestamp('starts_at')->nullable()->after('revocation_reason');
                }
                if (!Schema::hasColumn('event_worker_assignments', 'expires_at')) {
                    $table->timestamp('expires_at')->nullable()->after('starts_at');
                }
            });
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('password_reset_tokens');
    }
};
