<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * History of wallet provider operations (create, update, revoke, saved, removed, confirm), for the admin wallet page.
 * Holds only the outcome and a short error code: never tokens, links, keys or provider responses.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('wallet_pass_events', function (Blueprint $table) {
            $table->id();
            $table->uuid('wallet_pass_id')->index();
            $table->string('provider', 16);
            $table->string('action', 24);
            $table->string('outcome', 16);
            $table->string('error_code', 120)->nullable();
            $table->boolean('retryable')->default(false);
            $table->timestamp('created_at')->useCurrent();
            $table->index(['provider', 'created_at'], 'wallet_pass_events_provider_time_index');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('wallet_pass_events');
    }
};
