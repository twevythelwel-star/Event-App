<?php

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
        Schema::create('notification_logs', function (Blueprint $table) {
            $table->uuid('id')->primary();
            
            // Safe Idempotency Key - prevents duplicate dispatches during queue retries
            $table->string('idempotency_key')->unique();
            
            $table->string('order_id')->nullable()->index();
            $table->string('order_number')->nullable()->index();
            $table->string('ticket_code')->nullable()->index();
            $table->string('customer_name');
            $table->string('recipient')->index(); // phone number or email address
            
            // Channel: email, sms, whatsapp
            $table->string('channel')->default('email');
            
            // 9 Lifecycle Triggers
            $table->enum('trigger', [
                'order_received',
                'payment_instructions',
                'transfer_submitted',
                'payment_verified',
                'payment_rejected',
                'cash_receipt_recorded',
                'ticket_issued_updated',
                'event_reminders',
                'pickup_instructions',
                'order_collection_completed'
            ])->index();
            
            $table->string('title');
            $table->text('message'); // SMS text or plain message
            $table->string('email_subject')->nullable();
            $table->longText('email_html')->nullable();
            
            // Delivery Lifecycle: queued -> sent -> delivered / failed
            // Invariant: Never marked 'delivered' merely because it was queued
            $table->enum('status', ['queued', 'sent', 'delivered', 'failed'])->default('queued')->index();
            
            $table->string('provider')->default('Twilio'); // Twilio, Postmark, Mailgun, SMTP
            $table->string('provider_message_id')->nullable()->index(); // Twilio SID or Email Message-ID
            
            $table->unsignedInteger('retry_count')->default(0);
            $table->boolean('retry_suppressed')->default(false); // Flagged if duplicate dispatch was blocked
            $table->text('failure_reason')->nullable();
            $table->json('attempts_history')->nullable(); // Detailed timeline of webhook updates
            
            $table->timestamp('queued_at')->nullable();
            $table->timestamp('sent_at')->nullable();
            $table->timestamp('delivered_at')->nullable();
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('notification_logs');
    }
};
