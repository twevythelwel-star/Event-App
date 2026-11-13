<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * One row per admitted person: an order for three tickets has three attendee tickets, each with its own
 * admission credential and QR code.
 *
 * - The credential itself is never stored. It is an HMAC of the row (id, version, nonce) under the application
 *   key, so the server can show it again on the ticket page, while a copy of the database alone cannot forge one.
 *   Only its SHA-256 hash is stored, for lookups at the gate.
 * - unique(order_id, sequence) makes issuing idempotent: a retried or concurrent issue can never add a second
 *   "ticket 2 of 3".
 * - The QR artwork is rendered separately from issuing (qr_status), so a failed render is retried without
 *   touching the tickets.
 *
 * Also adds the host's ticket QR design choice to events and the administrators' global QR controls.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('attendee_tickets', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->string('order_id', 64);
            $table->string('event_id', 64)->index();
            $table->string('organizer_id', 64)->nullable()->index();
            $table->string('order_item_id', 64)->nullable();
            $table->string('ticket_tier_id', 64)->nullable();
            $table->unsignedSmallInteger('sequence');
            $table->string('ticket_number', 40)->unique();
            $table->string('credential_nonce', 64);
            $table->string('token_hash', 64)->unique();
            $table->unsignedInteger('token_version')->default(1);
            $table->timestamp('token_rotated_at')->nullable();
            $table->timestamp('checked_in_at')->nullable();
            $table->unsignedBigInteger('checked_in_by')->nullable();
            $table->string('check_in_request_id', 64)->nullable();
            $table->string('qr_status', 16)->default('pending');
            $table->json('qr_design')->nullable();
            $table->string('qr_design_hash', 64)->nullable();
            $table->unsignedSmallInteger('qr_attempts')->default(0);
            $table->string('qr_failure_reason', 255)->nullable();
            $table->timestamp('qr_generated_at')->nullable();
            $table->timestamps();

            $table->unique(['order_id', 'sequence'], 'attendee_tickets_order_sequence_unique');
        });

        Schema::table('events', function (Blueprint $table) {
            $table->json('ticket_qr_design')->nullable();
        });

        Schema::table('system_platform_settings', function (Blueprint $table) {
            $table->json('ticket_qr_controls')->nullable();
        });
    }

    public function down(): void
    {
        Schema::table('system_platform_settings', function (Blueprint $table) {
            $table->dropColumn('ticket_qr_controls');
        });
        Schema::table('events', function (Blueprint $table) {
            $table->dropColumn('ticket_qr_design');
        });
        Schema::dropIfExists('attendee_tickets');
    }
};
