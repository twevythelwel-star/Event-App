<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Processor payments (Stripe, PayPal) and per-method configuration.
 *
 * - payments: one row per payment attempt with a processor or by hand. Holds the processor's own reference,
 *   never card numbers or security codes (those go straight from the browser to the processor).
 * - payment_webhook_events: every processor event received, unique per provider and event id, so a
 *   repeated delivery is recognised and never applied twice.
 * - host_payment_policies: explicit on/off per method, and the cash desk's over- and underpayment rules.
 * - cash_payments: who received the cash (as an account, not only a name), and cash tendered / change given.
 * - system_platform_settings.payment_processors: processors and processor methods the platform has approved.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (!Schema::hasTable('payments')) {
            Schema::create('payments', function (Blueprint $table) {
                $table->uuid('id')->primary();
                $table->foreignUuid('order_id')->constrained('orders')->cascadeOnDelete();
                $table->foreignUuid('organizer_id')->constrained('organizers')->cascadeOnDelete();
                $table->string('provider', 24);           // stripe, paypal
                $table->string('method', 32);             // card, cash_app_pay, paypal
                $table->string('status', 24)->default('pending'); // pending, processing, succeeded, failed, cancelled
                $table->unsignedBigInteger('amount_minor');
                $table->string('currency', 3);
                $table->unsignedBigInteger('amount_received_minor')->default(0);
                $table->string('provider_reference', 128)->nullable(); // PaymentIntent id, PayPal order id
                $table->string('provider_capture_id', 128)->nullable(); // PayPal capture id, Stripe latest charge
                $table->string('idempotency_key', 128)->unique();
                $table->string('failure_code', 64)->nullable();
                $table->string('failure_message', 255)->nullable();
                $table->json('metadata')->nullable();
                $table->timestamp('succeeded_at')->nullable();
                $table->timestamps();

                $table->unique(['provider', 'provider_reference']);
                $table->index(['order_id', 'status']);
            });
        }

        if (!Schema::hasTable('payment_webhook_events')) {
            Schema::create('payment_webhook_events', function (Blueprint $table) {
                $table->uuid('id')->primary();
                $table->string('provider', 24);
                $table->string('event_id', 128);
                $table->string('type', 96);
                $table->string('payload_sha256', 64);
                $table->string('result', 32)->default('received'); // received, applied, ignored, failed
                $table->string('note', 255)->nullable();
                $table->timestamp('processed_at')->nullable();
                $table->timestamps();

                $table->unique(['provider', 'event_id']);
            });
        }

        Schema::table('host_payment_policies', function (Blueprint $table) {
            foreach (['enable_card' => true, 'enable_cash_app_pay' => false, 'enable_paypal' => false,
                         'enable_zelle' => true, 'enable_cashapp' => true, 'enable_bank' => true] as $column => $default) {
                if (!Schema::hasColumn('host_payment_policies', $column)) {
                    $table->boolean($column)->default($default);
                }
            }
            if (!Schema::hasColumn('host_payment_policies', 'cash_overpayment_rule')) {
                $table->string('cash_overpayment_rule', 24)->default('refuse'); // refuse, give_change
            }
            if (!Schema::hasColumn('host_payment_policies', 'cash_underpayment_rule')) {
                $table->string('cash_underpayment_rule', 24)->default('allow_partial'); // allow_partial, refuse
            }
        });

        Schema::table('cash_payments', function (Blueprint $table) {
            if (!Schema::hasColumn('cash_payments', 'received_by_user_id')) {
                $table->foreignId('received_by_user_id')->nullable()->constrained('users')->nullOnDelete();
            }
            if (!Schema::hasColumn('cash_payments', 'amount_due_minor')) {
                $table->bigInteger('amount_due_minor')->nullable();
            }
            if (!Schema::hasColumn('cash_payments', 'amount_tendered_minor')) {
                $table->bigInteger('amount_tendered_minor')->nullable();
            }
            if (!Schema::hasColumn('cash_payments', 'change_given_minor')) {
                $table->bigInteger('change_given_minor')->nullable();
            }
        });

        Schema::table('system_platform_settings', function (Blueprint $table) {
            if (!Schema::hasColumn('system_platform_settings', 'payment_processors')) {
                $table->json('payment_processors')->nullable();
            }
        });
    }

    public function down(): void
    {
        Schema::table('system_platform_settings', function (Blueprint $table) {
            $table->dropColumn('payment_processors');
        });
        Schema::table('cash_payments', function (Blueprint $table) {
            $table->dropConstrainedForeignId('received_by_user_id');
            $table->dropColumn(['amount_due_minor', 'amount_tendered_minor', 'change_given_minor']);
        });
        Schema::table('host_payment_policies', function (Blueprint $table) {
            $table->dropColumn(['enable_card', 'enable_cash_app_pay', 'enable_paypal', 'enable_zelle', 'enable_cashapp',
                'enable_bank', 'cash_overpayment_rule', 'cash_underpayment_rule']);
        });
        Schema::dropIfExists('payment_webhook_events');
        Schema::dropIfExists('payments');
    }
};
