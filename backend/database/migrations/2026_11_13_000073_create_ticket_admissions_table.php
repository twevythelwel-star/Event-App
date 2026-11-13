<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * One row per admitted attendee ticket. The unique key on attendee_ticket_id is the database's own guarantee
 * that a ticket is admitted at most once: even if two scans got past the row locks, the second insert fails.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('ticket_admissions', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->string('attendee_ticket_id', 64)->unique();
            $table->string('order_id', 64)->index();
            $table->string('event_id', 64)->index();
            $table->unsignedBigInteger('admitted_by')->nullable();
            // attendee_ticket: the guest's own QR; order_pass: the order's pass admitted the whole party.
            $table->string('admitted_via', 20);
            $table->string('request_id', 64)->nullable();
            $table->timestamp('admitted_at');
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('ticket_admissions');
    }
};
