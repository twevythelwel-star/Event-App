<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * The history of every attendee ticket credential: issued, reissued (with the reason and who did it), revoked,
 * charged back. Append-only: a row is never edited or deleted, so the record of why a QR code stopped working
 * survives. Credentials themselves are never stored here.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('ticket_credential_events', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->string('order_id', 64)->index();
            $table->string('attendee_ticket_id', 64)->nullable()->index();
            $table->string('action', 24); // issued | reissued | revoked | chargeback
            $table->unsignedInteger('token_version')->nullable();
            $table->string('reason', 255)->nullable();
            $table->unsignedBigInteger('actor_user_id')->nullable();
            $table->string('actor_name', 160)->nullable();
            $table->timestamp('occurred_at');
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('ticket_credential_events');
    }
};
