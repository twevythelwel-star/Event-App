<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * One wallet_passes table for every wallet provider (Apple, Google, Samsung). Existing rows are Apple passes.
 *
 * status: issued (created on our side) -> saved (the provider confirmed it, or a valid signed pass file was delivered)
 *         -> revoked; failed when the provider kept refusing a sync (see last_error).
 * sync_fingerprint / synced_token_version / synced_event_hash record what the provider last received, so the
 * WalletPassManager can tell whether the event changed, the ticket was reissued, or nothing needs sending.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('wallet_passes', function (Blueprint $table) {
            $table->string('provider', 16)->default('apple')->after('user_id');
            $table->string('status', 16)->default('issued')->after('provider');
            // Google object id or Samsung refId. Not secret, not guessable (derived from the random serial number).
            $table->string('external_id', 191)->nullable()->after('serial_number');
            $table->timestamp('saved_at')->nullable();
            $table->timestamp('revoked_at')->nullable();
            $table->string('sync_fingerprint', 64)->nullable();
            $table->string('synced_event_hash', 64)->nullable();
            $table->unsignedInteger('synced_token_version')->nullable();
            $table->timestamp('last_synced_at')->nullable();
            // A short error code only (never a provider response body, URL or token).
            $table->string('last_error', 120)->nullable();
            // Provider routing data that is not secret, e.g. Samsung's cc2 country code.
            $table->json('provider_meta')->nullable();
            $table->index(['order_id', 'provider'], 'wallet_passes_order_provider_index');
            $table->unique(['provider', 'external_id'], 'wallet_passes_provider_external_unique');
        });
    }

    public function down(): void
    {
        Schema::table('wallet_passes', function (Blueprint $table) {
            $table->dropUnique('wallet_passes_provider_external_unique');
            $table->dropIndex('wallet_passes_order_provider_index');
            $table->dropColumn(['provider', 'status', 'external_id', 'saved_at', 'revoked_at', 'sync_fingerprint',
                'synced_event_hash', 'synced_token_version', 'last_synced_at', 'last_error', 'provider_meta']);
        });
    }
};
