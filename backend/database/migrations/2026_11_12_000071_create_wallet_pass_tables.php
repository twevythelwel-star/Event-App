<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Apple Wallet passes issued for tickets, and the devices registered for their updates
 * (Apple's PassKit web service protocol). A pass belongs to one ticket holder: a transfer voids the sender's pass.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('wallet_passes', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->string('order_id', 64)->index();
            $table->unsignedBigInteger('user_id')->nullable()->index();
            $table->string('pass_type_id', 128);
            $table->string('serial_number', 64)->unique();
            // Wallet sends this back on every web-service call; stored encrypted (it is embedded in each pass).
            $table->text('authentication_token');
            $table->timestamp('voided_at')->nullable();
            $table->string('void_reason', 64)->nullable();
            // Strictly increasing per pass: Last-Modified and the passesUpdatedSince tag.
            $table->timestamp('last_modified_at')->index();
            $table->timestamps();
        });

        Schema::create('wallet_device_registrations', function (Blueprint $table) {
            $table->id();
            $table->string('device_library_identifier', 128)->index();
            $table->string('push_token', 255);
            $table->uuid('wallet_pass_id')->index();
            $table->timestamps();
            $table->unique(['device_library_identifier', 'wallet_pass_id'], 'wallet_reg_device_pass_unique');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('wallet_device_registrations');
        Schema::dropIfExists('wallet_passes');
    }
};
