<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Integrity pass over the existing schema; no table is added and no record is changed.
 *
 * - Removes four plain indexes that duplicate a unique index on the same column.
 * - Adds indexes for filters the app runs on every page: published events by date, an event's orders by status,
 *   a person's assignment on an event, open disputes per order, an event's active menu, an actor's audit trail.
 * - Adds the foreign keys that were missing. History keeps its rows: a deleted order leaves its notification log
 *   in place with order_id cleared (order number and ticket code are stored on the log itself); configuration
 *   (fee rules) goes with its organizer or event, as orders and menus already do.
 * - On MySQL, adds CHECK constraints so money amounts cannot go negative and refunds cannot exceed what was paid.
 *   SQLite cannot add constraints to an existing table, so development databases rely on the application's checks.
 *
 * Before any key or constraint is added the existing rows are checked. If a row would break it, the migration
 * stops and names the problem; it never deletes, clears or rewrites data to make a constraint fit.
 * down() removes only what up() added.
 */
return new class extends Migration
{
    private const REDUNDANT_INDEXES = [
        'orders' => ['orders_ticket_code_index', 'orders_order_number_index'],
        'cash_payments' => ['cash_payments_receipt_number_index'],
        'organizers' => ['organizers_slug_index'],
    ];

    private const NEW_INDEXES = [
        'events' => ['events_status_date_idx' => ['status', 'event_date_iso']],
        'orders' => ['orders_event_status_idx' => ['event_id', 'status']],
        'event_worker_assignments' => ['ewa_event_user_idx' => ['event_id', 'user_id']],
        'ticket_disputes' => ['disputes_order_status_idx' => ['order_id', 'status']],
        'menu_items' => ['menu_items_event_active_idx' => ['event_id', 'is_active']],
        'security_audit_logs' => ['audit_user_created_idx' => ['user_id', 'created_at']],
    ];

    /** table => [constraint name => SQL condition] (MySQL / MariaDB / PostgreSQL only) */
    private const CHECKS = [
        'orders' => [
            'orders_money_nonneg_chk' => 'subtotal_minor >= 0 AND discount_minor >= 0 AND fees_minor >= 0 AND tax_minor >= 0 AND total_minor >= 0 AND amount_paid_minor >= 0 AND refunded_minor >= 0',
            'orders_refund_le_paid_chk' => 'refunded_minor <= amount_paid_minor',
        ],
        'order_items' => [
            'order_items_qty_chk' => 'quantity >= 0 AND collected_quantity >= 0 AND collected_quantity <= quantity',
            'order_items_money_nonneg_chk' => 'unit_price_minor >= 0 AND options_minor >= 0 AND line_total_minor >= 0',
        ],
        'order_charges' => ['order_charges_amount_nonneg_chk' => 'amount_minor >= 0 AND basis_minor >= 0'],
        'order_adjustments' => ['order_adj_amount_pos_chk' => 'amount_minor > 0'],
        'ticket_disputes' => ['disputes_amount_nonneg_chk' => 'amount_minor >= 0'],
    ];

    public function up(): void
    {
        $this->assertNoOrphans();
        $this->assertChecksHold();

        foreach (self::REDUNDANT_INDEXES as $table => $indexes) {
            foreach ($indexes as $index) {
                if (Schema::hasIndex($table, $index)) {
                    Schema::table($table, fn (Blueprint $t) => $t->dropIndex($index));
                }
            }
        }

        foreach (self::NEW_INDEXES as $table => $indexes) {
            foreach ($indexes as $name => $columns) {
                if (!Schema::hasIndex($table, $name)) {
                    Schema::table($table, fn (Blueprint $t) => $t->index($columns, $name));
                }
            }
        }

        // notification_logs.order_id was a 255-character string; it holds order UUIDs, so it takes the same type
        // as every other order reference before it can point at orders.id (MySQL requires matching key types).
        Schema::table('notification_logs', function (Blueprint $t) {
            $t->uuid('order_id')->nullable()->change();
        });
        Schema::table('notification_logs', function (Blueprint $t) {
            $t->foreign('order_id', 'notif_logs_order_fk')->references('id')->on('orders')->nullOnDelete();
            $t->foreign('organizer_id', 'notif_logs_organizer_fk')->references('id')->on('organizers')->nullOnDelete();
        });
        Schema::table('order_adjustments', function (Blueprint $t) {
            $t->foreign('organizer_id', 'order_adj_organizer_fk')->references('id')->on('organizers')->cascadeOnDelete();
        });
        Schema::table('charge_rules', function (Blueprint $t) {
            $t->foreign('organizer_id', 'charge_rules_organizer_fk')->references('id')->on('organizers')->cascadeOnDelete();
            $t->foreign('event_id', 'charge_rules_event_fk')->references('id')->on('events')->cascadeOnDelete();
        });

        if ($this->supportsChecks()) {
            foreach (self::CHECKS as $table => $checks) {
                foreach ($checks as $name => $condition) {
                    DB::statement("ALTER TABLE {$table} ADD CONSTRAINT {$name} CHECK ({$condition})");
                }
            }
        }
    }

    public function down(): void
    {
        if ($this->supportsChecks()) {
            $drop = DB::getDriverName() === 'pgsql' ? 'DROP CONSTRAINT' : 'DROP CHECK';
            foreach (self::CHECKS as $table => $checks) {
                foreach (array_keys($checks) as $name) {
                    DB::statement("ALTER TABLE {$table} {$drop} {$name}");
                }
            }
        }

        // SQLite only drops foreign keys by column; the other engines by name.
        $key = fn (string $name, string $column) => DB::getDriverName() === 'sqlite' ? [$column] : $name;
        Schema::table('charge_rules', function (Blueprint $t) use ($key) {
            $t->dropForeign($key('charge_rules_organizer_fk', 'organizer_id'));
            $t->dropForeign($key('charge_rules_event_fk', 'event_id'));
        });
        Schema::table('order_adjustments', fn (Blueprint $t) => $t->dropForeign($key('order_adj_organizer_fk', 'organizer_id')));
        Schema::table('notification_logs', function (Blueprint $t) use ($key) {
            $t->dropForeign($key('notif_logs_order_fk', 'order_id'));
            $t->dropForeign($key('notif_logs_organizer_fk', 'organizer_id'));
        });
        Schema::table('notification_logs', fn (Blueprint $t) => $t->string('order_id')->nullable()->change());

        foreach (self::NEW_INDEXES as $table => $indexes) {
            foreach (array_keys($indexes) as $name) {
                Schema::table($table, fn (Blueprint $t) => $t->dropIndex($name));
            }
        }
        Schema::table('orders', function (Blueprint $t) {
            $t->index('ticket_code', 'orders_ticket_code_index');
            $t->index('order_number', 'orders_order_number_index');
        });
        Schema::table('cash_payments', fn (Blueprint $t) => $t->index('receipt_number', 'cash_payments_receipt_number_index'));
        Schema::table('organizers', fn (Blueprint $t) => $t->index('slug', 'organizers_slug_index'));
    }

    private function supportsChecks(): bool
    {
        return in_array(DB::getDriverName(), ['mysql', 'mariadb', 'pgsql'], true);
    }

    /** Every reference about to get a foreign key must point at a row that exists. */
    private function assertNoOrphans(): void
    {
        $references = [
            ['notification_logs', 'order_id', 'orders'],
            ['notification_logs', 'organizer_id', 'organizers'],
            ['order_adjustments', 'organizer_id', 'organizers'],
            ['charge_rules', 'organizer_id', 'organizers'],
            ['charge_rules', 'event_id', 'events'],
        ];
        $problems = [];
        foreach ($references as [$table, $column, $parent]) {
            $orphans = DB::table($table)->whereNotNull($column)
                ->whereNotExists(fn ($q) => $q->select(DB::raw(1))->from($parent)->whereColumn("{$parent}.id", "{$table}.{$column}"))
                ->count();
            if ($orphans > 0) {
                $problems[] = "{$orphans} row(s) in {$table}.{$column} point to a missing {$parent} row";
            }
        }
        if ($problems !== []) {
            throw new RuntimeException('Cannot add foreign keys until these references are reviewed (no data was changed): ' . implode('; ', $problems) . '.');
        }
    }

    /** The money checks must already hold for every existing row; history is never edited to make them fit. */
    private function assertChecksHold(): void
    {
        if (!$this->supportsChecks()) {
            return;
        }
        $problems = [];
        foreach (self::CHECKS as $table => $checks) {
            foreach ($checks as $name => $condition) {
                $violations = DB::table($table)->whereRaw("NOT ({$condition})")->count();
                if ($violations > 0) {
                    $problems[] = "{$violations} row(s) in {$table} fail {$name}";
                }
            }
        }
        if ($problems !== []) {
            throw new RuntimeException('Cannot add money constraints until these rows are reviewed (no data was changed): ' . implode('; ', $problems) . '.');
        }
    }
};
