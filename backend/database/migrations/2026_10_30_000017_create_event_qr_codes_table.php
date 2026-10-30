<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Additive only. One promotional QR identity per event:
     * - public_code is the opaque, stable identifier the QR encodes (/e/{public_code}); it never changes,
     *   so printed flyers keep working after a redesign.
     * - design holds the approved visual configuration; design_version increases on each explicit redesign.
     * - status tracks artwork generation (pending, ready, failed) so a failed render can be retried
     *   without creating a second identity (event_id is unique).
     * The artwork itself lives on the private storage disk at asset_path, never in the database.
     */
    public function up(): void
    {
        if (Schema::hasTable('event_qr_codes')) {
            return;
        }

        Schema::create('event_qr_codes', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->uuid('event_id')->unique();
            $table->string('public_code', 16)->unique();
            $table->string('preset', 32)->nullable();
            $table->json('design')->nullable();
            $table->unsignedInteger('design_version')->default(0);
            $table->string('status', 16)->default('pending');
            $table->string('asset_path')->nullable();
            $table->string('failure_reason', 255)->nullable();
            $table->unsignedInteger('attempts')->default(0);
            $table->timestamp('generated_at')->nullable();
            $table->timestamps();

            $table->foreign('event_id')->references('id')->on('events')->cascadeOnDelete();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('event_qr_codes');
    }
};
