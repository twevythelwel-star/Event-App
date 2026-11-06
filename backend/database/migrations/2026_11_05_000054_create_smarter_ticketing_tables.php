<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Smarter ticketing, additive and forward-only:
 * - ticket_holds: tickets set aside for a buyer while they check out (or for a waitlisted buyer to claim);
 * - discount_codes / discount_redemptions: promotional codes with limits and dates, and each use of one;
 * - affiliates: promoters with a link code and commission; orders remember the affiliate and the code used;
 * - ticket_transfers: a holder passing their tickets to someone else, accepted by the recipient;
 * - waitlist_entries: buyers waiting for a sold-out ticket type, offered tickets in turn;
 * - complimentary tickets are ordinary orders marked complimentary, with who issued them and why.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('events', function (Blueprint $table) {
            $table->boolean('allow_ticket_transfers')->default(true);
            $table->boolean('waitlist_enabled')->default(true);
        });

        Schema::create('ticket_holds', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->uuid('event_id');
            $table->string('ticket_tier_id', 64);
            $table->unsignedSmallInteger('quantity');
            $table->unsignedBigInteger('user_id');
            $table->string('source', 12)->default('checkout'); // checkout, waitlist
            $table->timestamp('expires_at');
            $table->timestamps();
            $table->foreign('event_id')->references('id')->on('events')->cascadeOnDelete();
            $table->index(['event_id', 'ticket_tier_id', 'expires_at']);
            $table->index(['user_id', 'event_id']);
        });

        Schema::create('discount_codes', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->uuid('event_id');
            $table->string('code', 40);
            $table->string('description', 200)->nullable();
            $table->string('kind', 10); // percent, fixed
            $table->unsignedInteger('amount'); // percent (1–100) or minor units
            $table->string('applies_to', 10)->default('tickets'); // tickets, food, all
            $table->json('ticket_tier_ids')->nullable();
            $table->unsignedSmallInteger('min_tickets')->nullable();
            $table->unsignedInteger('max_uses')->nullable();
            $table->unsignedSmallInteger('max_uses_per_customer')->nullable();
            $table->timestamp('starts_at')->nullable();
            $table->timestamp('ends_at')->nullable();
            $table->uuid('affiliate_id')->nullable();
            $table->boolean('is_active')->default(true);
            $table->timestamps();
            $table->foreign('event_id')->references('id')->on('events')->cascadeOnDelete();
            $table->unique(['event_id', 'code']);
        });

        Schema::create('affiliates', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->uuid('event_id');
            $table->string('name', 120);
            $table->string('code', 40);
            $table->string('email', 190)->nullable();
            $table->decimal('commission_percent', 5, 2)->default(0);
            $table->boolean('is_active')->default(true);
            $table->timestamps();
            $table->foreign('event_id')->references('id')->on('events')->cascadeOnDelete();
            $table->unique(['event_id', 'code']);
        });

        Schema::create('discount_redemptions', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->uuid('discount_code_id');
            $table->uuid('order_id');
            $table->unsignedBigInteger('user_id')->nullable();
            $table->unsignedBigInteger('amount_minor');
            $table->timestamps();
            $table->foreign('discount_code_id')->references('id')->on('discount_codes')->cascadeOnDelete();
            $table->unique('order_id');
        });

        Schema::table('orders', function (Blueprint $table) {
            $table->uuid('affiliate_id')->nullable();
            $table->uuid('discount_code_id')->nullable();
            $table->boolean('is_complimentary')->default(false);
            $table->unsignedBigInteger('issued_by')->nullable();
            $table->string('complimentary_reason', 200)->nullable();
            $table->index('affiliate_id');
        });

        Schema::create('ticket_transfers', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->uuid('order_id');
            $table->unsignedBigInteger('from_user_id');
            $table->string('to_email', 190);
            $table->unsignedBigInteger('to_user_id')->nullable();
            $table->string('token_hash', 64);
            $table->string('status', 12)->default('pending'); // pending, accepted, cancelled, expired
            $table->timestamp('expires_at');
            $table->timestamp('accepted_at')->nullable();
            $table->timestamps();
            $table->index(['order_id', 'status']);
            $table->index('to_email');
        });

        Schema::create('waitlist_entries', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->uuid('event_id');
            $table->string('ticket_tier_id', 64);
            $table->unsignedBigInteger('user_id');
            $table->unsignedSmallInteger('quantity');
            $table->string('status', 12)->default('waiting'); // waiting, offered, purchased, expired, left
            $table->uuid('hold_id')->nullable();
            $table->timestamp('offered_at')->nullable();
            $table->timestamp('offer_expires_at')->nullable();
            $table->timestamps();
            $table->foreign('event_id')->references('id')->on('events')->cascadeOnDelete();
            $table->index(['event_id', 'ticket_tier_id', 'status', 'created_at']);
            $table->index('user_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('waitlist_entries');
        Schema::dropIfExists('ticket_transfers');
        Schema::table('orders', function (Blueprint $table) {
            $table->dropIndex(['affiliate_id']);
            $table->dropColumn(['affiliate_id', 'discount_code_id', 'is_complimentary', 'issued_by', 'complimentary_reason']);
        });
        Schema::dropIfExists('discount_redemptions');
        Schema::dropIfExists('affiliates');
        Schema::dropIfExists('discount_codes');
        Schema::dropIfExists('ticket_holds');
        Schema::table('events', function (Blueprint $table) {
            $table->dropColumn(['allow_ticket_transfers', 'waitlist_enabled']);
        });
    }
};
