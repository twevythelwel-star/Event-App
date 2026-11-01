<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * notification_logs.trigger was an enum of the first ten moments, so new customer messages (payment awaiting
 * verification, ticket issued, event updated, refund requested and completed) could not be recorded. It becomes
 * a plain string; NotificationService::TRIGGERS is the list of allowed values. Existing rows are unchanged.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('notification_logs', function (Blueprint $table) {
            $table->string('trigger', 48)->change();
        });
    }

    public function down(): void
    {
        // Rows using the newer triggers would not fit the old list, so they block this rollback.
        Schema::table('notification_logs', function (Blueprint $table) {
            $table->enum('trigger', [
                'order_received', 'payment_instructions', 'transfer_submitted', 'payment_verified', 'payment_rejected',
                'cash_receipt_recorded', 'ticket_issued_updated', 'event_reminders', 'pickup_instructions', 'order_collection_completed',
            ])->change();
        });
    }
};
