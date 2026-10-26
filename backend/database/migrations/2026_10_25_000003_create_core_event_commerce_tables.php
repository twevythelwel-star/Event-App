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
        // 1. Events Table
        Schema::create('events', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('organizer_id')->constrained('organizers')->cascadeOnDelete();
            $table->string('slug');
            $table->string('title');
            $table->string('tagline')->nullable();
            $table->string('date_str');
            $table->string('time_str');
            $table->string('timezone', 64)->default('America/Jamaica');
            $table->string('currency', 8)->default('JMD');
            $table->string('status', 24)->default('draft'); // draft, published, unpublished, cancelled
            $table->string('venue_name');
            $table->text('venue_address');
            $table->string('coordinates')->nullable();
            $table->text('description')->nullable();
            $table->string('organizer_name');
            $table->string('organizer_phone');
            $table->string('organizer_whatsapp')->nullable();
            $table->string('organizer_email')->nullable();
            $table->timestamp('advance_order_deadline');
            $table->boolean('is_ordering_open')->default(true);
            $table->text('pickup_instructions')->nullable();
            $table->decimal('packaging_fee_usd', 8, 2)->default(0.00);
            $table->unsignedInteger('packaging_fee_jmd')->default(0);
            $table->string('flyer_path')->nullable();
            $table->string('flyer_thumbnail_path')->nullable();
            $table->string('flyer_mime_type', 64)->nullable();
            $table->unsignedInteger('flyer_size_bytes')->nullable();
            $table->unsignedInteger('flyer_width')->nullable();
            $table->unsignedInteger('flyer_height')->nullable();
            $table->boolean('is_flyer_public')->default(false);
            $table->timestamps();
            $table->softDeletes();

            $table->unique('slug');
            $table->index(['organizer_id', 'is_ordering_open']);
            $table->index(['organizer_id', 'status']);
            $table->index('advance_order_deadline');
        });

        // 2. Menu Items Table
        Schema::create('menu_items', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('organizer_id')->constrained('organizers')->cascadeOnDelete();
            $table->foreignUuid('event_id')->nullable()->constrained('events')->cascadeOnDelete();
            $table->string('name');
            $table->string('category', 64)->default('Mains');
            $table->text('description')->nullable();
            $table->string('pricing_type', 32)->default('fixed'); // fixed, customizable, custom_amount
            $table->decimal('price_usd', 8, 2)->default(0.00);
            $table->unsignedInteger('price_jmd')->default(0);
            $table->string('image_url')->nullable();
            $table->json('custom_option_groups')->nullable();
            $table->json('custom_amount_config')->nullable();
            $table->boolean('allow_special_instructions')->default(true);
            $table->unsignedInteger('available_stock')->nullable(); // null means uncapped
            $table->unsignedInteger('sort_order')->default(0);
            $table->boolean('is_active')->default(true);
            $table->timestamps();
            $table->softDeletes();

            $table->index(['organizer_id', 'category', 'is_active']);
            $table->index(['event_id', 'sort_order']);
        });

        // 3. Orders Table
        Schema::create('orders', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('organizer_id')->constrained('organizers')->cascadeOnDelete();
            $table->foreignUuid('event_id')->nullable()->constrained('events')->nullOnDelete();
            $table->string('ticket_code', 32)->unique();
            $table->string('order_number', 64)->unique();
            $table->string('customer_name');
            $table->string('customer_phone', 32);
            $table->string('email')->nullable();
            $table->string('method', 32); // bank, zelle, cashapp, cash, card
            $table->decimal('subtotal', 10, 2);
            $table->decimal('packaging_fee', 8, 2)->default(0.00);
            $table->decimal('total_amount', 10, 2);
            $table->decimal('amount_paid', 10, 2)->default(0.00);
            $table->string('currency', 8)->default('JMD');
            $table->string('status', 32)->default('awaiting_payment');
            $table->string('fulfillment_status', 32)->default('unprepared');
            $table->string('payment_reference')->nullable();
            $table->string('sender_name')->nullable();
            $table->timestamp('verified_at')->nullable();
            $table->string('verified_by')->nullable();
            $table->text('verification_notes')->nullable();
            $table->text('rejection_reason')->nullable();
            $table->json('refund_details')->nullable();
            $table->boolean('authorized_unpaid')->default(false);
            $table->string('secure_ticket_token', 255)->unique();
            $table->boolean('all_handed_over')->default(false);
            $table->timestamp('closed_at')->nullable();
            $table->text('notes')->nullable();
            $table->timestamps();
            $table->softDeletes();

            $table->index(['organizer_id', 'status']);
            $table->index(['organizer_id', 'fulfillment_status']);
            $table->index('customer_phone');
            $table->index('order_number');
            $table->index('ticket_code');
        });

        // 4. Order Items Table
        Schema::create('order_items', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('order_id')->constrained('orders')->cascadeOnDelete();
            $table->foreignUuid('menu_item_id')->nullable()->constrained('menu_items')->nullOnDelete();
            $table->string('name');
            $table->string('category', 64)->nullable();
            $table->string('pricing_type', 32)->default('fixed');
            $table->decimal('base_price', 10, 2);
            $table->json('selected_options_summary')->nullable();
            $table->decimal('options_total_extra', 8, 2)->default(0.00);
            $table->decimal('custom_donation_amount', 10, 2)->nullable();
            $table->decimal('final_item_price', 10, 2);
            $table->string('currency', 8)->default('JMD');
            $table->unsignedInteger('quantity')->default(1);
            $table->unsignedInteger('collected_quantity')->default(0);
            $table->text('special_instructions')->nullable();
            $table->string('handover_status', 32)->default('pending'); // pending, collected
            $table->timestamp('handed_over_at')->nullable();
            $table->string('handed_over_by')->nullable();
            $table->string('station_handed_over')->nullable();
            $table->timestamps();

            $table->index(['order_id', 'handover_status']);
        });

        // 5. Cash Payments & Reversals Ledger
        Schema::create('cash_payments', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('organizer_id')->constrained('organizers')->cascadeOnDelete();
            $table->foreignUuid('order_id')->nullable()->constrained('orders')->nullOnDelete();
            $table->string('transaction_type', 32)->default('receipt'); // receipt, reversal, adjustment
            $table->string('receipt_number', 64)->unique();
            $table->string('customer_name');
            $table->string('customer_phone', 32);
            $table->string('event_name')->nullable();
            $table->string('order_number', 64)->nullable();
            $table->string('ticket_code', 32)->nullable();
            $table->decimal('amount', 10, 2);
            $table->string('currency', 8)->default('JMD');
            $table->timestamp('received_at');
            $table->string('received_by');
            $table->text('notes')->nullable();
            $table->foreignUuid('original_record_id')->nullable()->constrained('cash_payments')->nullOnDelete();
            $table->string('reversal_of_id')->nullable();
            $table->text('reversal_reason')->nullable();
            $table->string('reversal_authorized_by')->nullable();
            $table->timestamps();

            $table->index(['organizer_id', 'transaction_type']);
            $table->index('receipt_number');
            $table->index('order_id');
        });

        // 6. Handover Audit Logs
        Schema::create('handover_audit_logs', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('organizer_id')->constrained('organizers')->cascadeOnDelete();
            $table->foreignUuid('order_id')->constrained('orders')->cascadeOnDelete();
            $table->string('order_number', 64);
            $table->string('ticket_code', 32);
            $table->string('customer_name');
            $table->string('item_name');
            $table->string('item_category', 64)->nullable();
            $table->unsignedInteger('quantity_collected');
            $table->unsignedInteger('remaining_quantity');
            $table->timestamp('timestamp');
            $table->string('staff_name');
            $table->string('station', 64);
            $table->string('action_type', 32)->default('collection'); // collection, reversal, supervisor_override
            $table->text('override_reason')->nullable();
            $table->string('supervisor_name')->nullable();
            $table->timestamps();

            $table->index(['organizer_id', 'order_id']);
            $table->index(['order_number', 'timestamp']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('handover_audit_logs');
        Schema::dropIfExists('cash_payments');
        Schema::dropIfExists('order_items');
        Schema::dropIfExists('orders');
        Schema::dropIfExists('menu_items');
        Schema::dropIfExists('events');
    }
};
