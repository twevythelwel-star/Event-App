<?php

use App\Support\Currency;
use App\Support\Money;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;

/**
 * Money in integer minor units, configurable fees and taxes, and an auditable adjustments ledger.
 *
 * Additive (expand phase). The legacy decimal columns stay and are kept in step by the application, so
 * anything still reading them keeps working; every calculation now uses the *_minor columns.
 *
 * - *_minor bigint columns on orders, order_items, menu_items, events, cash_payments and ticket_disputes,
 *   backfilled exactly from the decimals (parsed as text, never through floating point).
 * - orders gains discount_minor, fees_minor and tax_minor, so item subtotal, discounts, fees, taxes and
 *   total are stored separately.
 * - charge_rules: platform fees, host fees, taxes and discounts as configuration, scoped to the platform, an
 *   organizer or an event, and optionally a US state. No rates are seeded: taxes are whatever an
 *   administrator or host configures, not a statement of what the law requires.
 * - order_charges: the fee, tax and discount lines applied to each order, frozen at checkout.
 * - order_adjustments: refunds and other money corrections as one row each (backfilled from refund_details).
 * - Currency column defaults move from JMD to the configured default (USD). Existing rows keep their currency.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('orders', function (Blueprint $table) {
            $table->bigInteger('subtotal_minor')->default(0);
            $table->bigInteger('discount_minor')->default(0);
            $table->bigInteger('fees_minor')->default(0);
            $table->bigInteger('tax_minor')->default(0);
            $table->bigInteger('total_minor')->default(0);
            $table->bigInteger('amount_paid_minor')->default(0);
            $table->bigInteger('refunded_minor')->default(0);
        });

        Schema::table('order_items', function (Blueprint $table) {
            $table->bigInteger('unit_price_minor')->default(0);
            $table->bigInteger('options_minor')->default(0);
            $table->bigInteger('line_total_minor')->default(0);
        });

        Schema::table('menu_items', function (Blueprint $table) {
            $table->bigInteger('price_minor')->nullable();
            $table->string('currency', 3)->nullable();
        });

        Schema::table('events', function (Blueprint $table) {
            $table->bigInteger('packaging_fee_minor')->default(0);
        });

        Schema::table('cash_payments', function (Blueprint $table) {
            $table->bigInteger('amount_minor')->default(0);
        });

        Schema::table('ticket_disputes', function (Blueprint $table) {
            $table->bigInteger('amount_minor')->default(0);
        });

        Schema::create('charge_rules', function (Blueprint $table) {
            $table->uuid('id')->primary();
            // Scope: platform-wide (both null), one organizer, or one event
            $table->uuid('organizer_id')->nullable()->index();
            $table->uuid('event_id')->nullable()->index();
            $table->string('kind', 20);              // platform_fee | host_fee | tax | discount
            $table->string('label', 120);            // what the customer sees, e.g. "Service fee", "Sales tax (configured)"
            $table->string('method', 20);            // percent | fixed_per_order | fixed_per_item
            $table->unsignedInteger('rate_bp')->nullable();       // percent: basis points, 825 = 8.25%
            $table->bigInteger('amount_minor')->nullable();        // fixed methods
            $table->string('currency', 3)->nullable();             // required for fixed methods
            $table->string('applies_to', 10)->default('all');      // all | tickets | food
            $table->char('state_code', 2)->nullable();             // only events in this US state
            $table->boolean('is_active')->default(true);
            $table->unsignedSmallInteger('sort_order')->default(100);
            $table->text('notes')->nullable();                     // e.g. who verified the rate and when
            $table->timestamps();
            $table->index(['is_active', 'kind']);
        });

        Schema::create('order_charges', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->uuid('order_id')->index();
            $table->string('kind', 20);               // platform_fee | host_fee | tax | discount
            $table->string('label', 120);
            $table->uuid('charge_rule_id')->nullable();
            $table->string('method', 20)->nullable();
            $table->unsignedInteger('rate_bp')->nullable();
            $table->bigInteger('basis_minor')->default(0);   // what the rate was applied to
            $table->bigInteger('amount_minor');              // positive; discounts are subtracted from the total
            $table->string('currency', 3);
            $table->timestamps();
            $table->foreign('order_id')->references('id')->on('orders')->cascadeOnDelete();
        });

        Schema::create('order_adjustments', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->uuid('order_id')->index();
            $table->uuid('organizer_id')->nullable()->index();
            $table->string('type', 20);                // refund | correction
            $table->bigInteger('amount_minor');        // positive amount returned to (or corrected for) the customer
            $table->string('currency', 3);
            $table->text('reason');
            $table->unsignedBigInteger('actor_user_id')->nullable();
            $table->string('actor_name', 255)->nullable();
            $table->json('metadata')->nullable();
            $table->timestamp('occurred_at');
            $table->timestamps();
            $table->foreign('order_id')->references('id')->on('orders')->cascadeOnDelete();
        });

        $default = Currency::defaultCode();
        foreach (['events' => 'currency', 'orders' => 'currency', 'order_items' => 'currency', 'cash_payments' => 'currency', 'ticket_disputes' => 'currency'] as $tableName => $column) {
            Schema::table($tableName, function (Blueprint $table) use ($column, $default) {
                $table->string($column, 8)->default($default)->change();
            });
        }
        Schema::table('organizers', fn (Blueprint $t) => $t->string('default_currency', 3)->default($default)->change());
        Schema::table('host_event_settings', fn (Blueprint $t) => $t->string('default_currency', 4)->default($default)->change());
        Schema::table('users', fn (Blueprint $t) => $t->string('preferred_display_currency', 4)->default($default)->change());

        $this->backfill();
    }

    public function down(): void
    {
        Schema::dropIfExists('order_adjustments');
        Schema::dropIfExists('order_charges');
        Schema::dropIfExists('charge_rules');
        Schema::table('ticket_disputes', fn (Blueprint $t) => $t->dropColumn('amount_minor'));
        Schema::table('cash_payments', fn (Blueprint $t) => $t->dropColumn('amount_minor'));
        Schema::table('events', fn (Blueprint $t) => $t->dropColumn('packaging_fee_minor'));
        Schema::table('menu_items', fn (Blueprint $t) => $t->dropColumn(['price_minor', 'currency']));
        Schema::table('order_items', fn (Blueprint $t) => $t->dropColumn(['unit_price_minor', 'options_minor', 'line_total_minor']));
        Schema::table('orders', fn (Blueprint $t) => $t->dropColumn(['subtotal_minor', 'discount_minor', 'fees_minor', 'tax_minor', 'total_minor', 'amount_paid_minor', 'refunded_minor']));
    }

    private function backfill(): void
    {
        $minor = fn ($value, ?string $currency): int => Money::parse((string) ($value ?? '0'), $this->currency($currency))->minor();

        DB::table('events')->orderBy('id')->chunk(200, function ($events) use ($minor) {
            foreach ($events as $e) {
                $currency = $this->currency($e->currency);
                $fee = $currency === 'JMD' ? $e->packaging_fee_jmd : $e->packaging_fee_usd;
                DB::table('events')->where('id', $e->id)->update(['packaging_fee_minor' => $minor($fee, $currency)]);
            }
        });

        $eventCurrency = DB::table('events')->pluck('currency', 'id');
        DB::table('menu_items')->orderBy('id')->chunk(200, function ($items) use ($minor, $eventCurrency) {
            foreach ($items as $i) {
                $currency = $this->currency($eventCurrency[$i->event_id] ?? null);
                $price = $currency === 'JMD' ? $i->price_jmd : $i->price_usd;
                DB::table('menu_items')->where('id', $i->id)->update(['price_minor' => $minor($price, $currency), 'currency' => $currency]);
            }
        });

        DB::table('order_items')->orderBy('id')->chunk(200, function ($items) use ($minor) {
            foreach ($items as $i) {
                $unit = $minor($i->final_item_price, $i->currency);
                DB::table('order_items')->where('id', $i->id)->update([
                    'unit_price_minor' => $unit,
                    'options_minor' => $minor($i->options_total_extra, $i->currency),
                    'line_total_minor' => $unit * (int) $i->quantity,
                ]);
            }
        });

        DB::table('orders')->orderBy('id')->chunk(200, function ($orders) use ($minor) {
            foreach ($orders as $o) {
                $currency = $this->currency($o->currency);
                $fee = $minor($o->packaging_fee, $currency);
                $refunds = json_decode((string) ($o->refund_details ?? ''), true);
                $refunds = is_array($refunds) ? (array_is_list($refunds) ? $refunds : [$refunds]) : [];
                $refunded = 0;
                foreach ($refunds as $entry) {
                    if (!is_array($entry)) {
                        continue;
                    }
                    $amount = isset($entry['amount']) ? $minor($entry['amount'], $currency) : (int) ($entry['amount_minor'] ?? 0);
                    if ($amount <= 0) {
                        continue;
                    }
                    $refunded += $amount;
                    DB::table('order_adjustments')->insert([
                        'id' => (string) Str::uuid(),
                        'order_id' => $o->id,
                        'organizer_id' => $o->organizer_id,
                        'type' => 'refund',
                        'amount_minor' => $amount,
                        'currency' => $currency,
                        'reason' => (string) ($entry['reason'] ?? 'Refund (migrated from refund history)'),
                        'actor_name' => isset($entry['refundedBy']) ? (string) $entry['refundedBy'] : null,
                        'metadata' => json_encode(['migrated_from' => 'refund_details']),
                        'occurred_at' => $entry['refundedAt'] ?? $o->updated_at ?? now(),
                        'created_at' => now(),
                        'updated_at' => now(),
                    ]);
                }
                if ($fee > 0) {
                    DB::table('order_charges')->insert([
                        'id' => (string) Str::uuid(),
                        'order_id' => $o->id,
                        'kind' => 'host_fee',
                        'label' => 'Packaging fee',
                        'method' => 'fixed_per_order',
                        'basis_minor' => 0,
                        'amount_minor' => $fee,
                        'currency' => $currency,
                        'created_at' => now(),
                        'updated_at' => now(),
                    ]);
                }
                DB::table('orders')->where('id', $o->id)->update([
                    'subtotal_minor' => $minor($o->subtotal, $currency),
                    'fees_minor' => $fee,
                    'total_minor' => $minor($o->total_amount, $currency),
                    'amount_paid_minor' => $minor($o->amount_paid, $currency),
                    'refunded_minor' => $refunded,
                ]);
            }
        });

        DB::table('cash_payments')->orderBy('id')->chunk(200, function ($rows) use ($minor) {
            foreach ($rows as $r) {
                DB::table('cash_payments')->where('id', $r->id)->update(['amount_minor' => $minor($r->amount, $r->currency)]);
            }
        });

        DB::table('ticket_disputes')->orderBy('id')->chunk(200, function ($rows) use ($minor) {
            foreach ($rows as $r) {
                DB::table('ticket_disputes')->where('id', $r->id)->update(['amount_minor' => $minor($r->amount, $r->currency)]);
            }
        });
    }

    /** Rows with a currency this installation doesn't define are converted at the default precision. */
    private function currency(?string $code): string
    {
        $code = strtoupper(trim((string) $code));

        return Currency::isSupported($code) ? $code : Currency::defaultCode();
    }
};
