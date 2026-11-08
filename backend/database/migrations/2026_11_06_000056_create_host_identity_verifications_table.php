<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (!Schema::hasTable('host_identity_verifications')) {
            Schema::create('host_identity_verifications', function (Blueprint $table) {
                $table->uuid('id')->primary();
                $table->unsignedBigInteger('user_id')->index();
                $table->unsignedBigInteger('host_application_id')->nullable()->index();
                $table->string('status', 48)->default('not_submitted')->index();
                $table->string('document_type', 64)->nullable();
                $table->string('document_type_other_note', 255)->nullable();
                $table->string('legal_full_name', 255)->nullable();
                $table->string('issuing_country', 16)->default('US');
                $table->string('document_last4', 8)->nullable();
                $table->date('document_expires_at')->nullable();

                // Private encrypted storage metadata (never public storage)
                $table->string('private_storage_disk', 32)->default('local');
                $table->string('encrypted_storage_path', 512)->nullable();
                $table->string('original_mime_type', 96)->nullable();
                $table->unsignedInteger('original_size_bytes')->nullable();
                $table->string('document_sha256', 64)->nullable();
                $table->string('encryption_cipher', 32)->default('AES-256-CBC');
                $table->string('malware_scan_status', 32)->default('clean');
                $table->unsignedInteger('replacement_count')->default(0);

                // Review & workflow lifecycle timestamps (UTC)
                $table->timestamp('submitted_at_utc')->nullable();
                $table->unsignedBigInteger('reviewed_by')->nullable()->index();
                $table->timestamp('reviewed_at_utc')->nullable();
                $table->text('review_notes')->nullable();
                $table->text('rejection_or_info_reason')->nullable();

                // Retention, deletion, and privacy controls
                $table->timestamp('consent_accepted_at_utc')->nullable();
                $table->timestamp('retention_expires_at_utc')->nullable()->index();
                $table->timestamp('document_purged_at_utc')->nullable();
                $table->string('purge_reason', 120)->nullable();
                $table->timestamps();
            });
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('host_identity_verifications');
    }
};

