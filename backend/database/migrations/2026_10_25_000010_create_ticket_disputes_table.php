<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Attendee disputes about an order (refund claims, missing food, payment problems), worked by administrators.
     */
    public function up(): void
    {
        Schema::create('ticket_disputes', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->string('dispute_number', 32)->unique();
            $table->foreignUuid('organizer_id')->constrained('organizers')->cascadeOnDelete();
            $table->foreignUuid('event_id')->nullable()->constrained('events')->nullOnDelete();
            $table->foreignUuid('order_id')->constrained('orders')->cascadeOnDelete();
            $table->foreignId('user_id')->constrained('users')->cascadeOnDelete(); // attendee who filed it
            $table->string('dispute_type', 32)->default('ticket_refund');
            $table->string('status', 24)->default('open'); // open, under_review, resolved, rejected
            $table->decimal('amount', 10, 2)->default(0);
            $table->string('currency', 8)->default('JMD');
            $table->text('reason');
            $table->foreignId('assigned_admin_id')->nullable()->constrained('users')->nullOnDelete();
            $table->string('assigned_admin_name')->nullable();
            $table->text('resolution_notes')->nullable();
            $table->timestamp('resolved_at')->nullable();
            $table->timestamps();

            $table->index(['status', 'created_at']);
            $table->index(['organizer_id', 'status']);
            $table->index('user_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('ticket_disputes');
    }
};
