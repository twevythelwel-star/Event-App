<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Referential-integrity pass over the smarter-ticketing tables; additive only, no data is changed.
 *
 * - ticket_transfers.token_hash is the lookup key for accepting a transfer but had neither a unique
 *   constraint nor an index: lookups scanned the table and nothing stopped a duplicate hash.
 * - Foreign keys that 000054 left off: transfers -> orders/users, discount_redemptions -> orders,
 *   orders -> affiliates/discount_codes/users (issued_by), waitlist_entries -> ticket_holds.
 * - security_audit_logs gets (action, created_at): the fraud monitor and audit filters query
 *   action + time-range on every load.
 *
 * Before any constraint is added, existing rows are checked; if a row would break it the migration
 * stops and names the problem — it never deletes or rewrites data to make a constraint fit.
 * down() removes only what up() added.
 */
return new class extends Migration
{
    private const NEW_INDEXES = [
        'security_audit_logs' => ['audit_action_created_idx' => ['action', 'created_at']],
    ];

    public function up(): void
    {
        $this->assertNoOrphans();
        $this->assertUniqueTransferTokens();

        Schema::table('ticket_transfers', function (Blueprint $table) {
            $table->unique('token_hash', 'ticket_transfers_token_hash_unique');
            $table->foreign('order_id', 'ticket_transfers_order_fk')->references('id')->on('orders')->cascadeOnDelete();
            $table->foreign('from_user_id', 'ticket_transfers_from_user_fk')->references('id')->on('users')->cascadeOnDelete();
            $table->foreign('to_user_id', 'ticket_transfers_to_user_fk')->references('id')->on('users')->nullOnDelete();
        });

        Schema::table('discount_redemptions', function (Blueprint $table) {
            $table->foreign('order_id', 'discount_redemptions_order_fk')->references('id')->on('orders')->cascadeOnDelete();
        });

        Schema::table('orders', function (Blueprint $table) {
            $table->foreign('affiliate_id', 'orders_affiliate_fk')->references('id')->on('affiliates')->nullOnDelete();
            $table->foreign('discount_code_id', 'orders_discount_code_fk')->references('id')->on('discount_codes')->nullOnDelete();
            $table->foreign('issued_by', 'orders_issued_by_fk')->references('id')->on('users')->nullOnDelete();
        });

        Schema::table('waitlist_entries', function (Blueprint $table) {
            $table->foreign('hold_id', 'waitlist_entries_hold_fk')->references('id')->on('ticket_holds')->nullOnDelete();
        });

        foreach (self::NEW_INDEXES as $table => $indexes) {
            foreach ($indexes as $name => $columns) {
                if (!Schema::hasIndex($table, $name)) {
                    Schema::table($table, fn (Blueprint $t) => $t->index($columns, $name));
                }
            }
        }
    }

    public function down(): void
    {
        foreach (self::NEW_INDEXES as $table => $indexes) {
            foreach (array_keys($indexes) as $name) {
                if (Schema::hasIndex($table, $name)) {
                    Schema::table($table, fn (Blueprint $t) => $t->dropIndex($name));
                }
            }
        }

        // SQLite only drops foreign keys by column; the other engines by name.
        $key = fn (string $name, string $column) => DB::getDriverName() === 'sqlite' ? [$column] : $name;

        Schema::table('waitlist_entries', function (Blueprint $table) use ($key) {
            $table->dropForeign($key('waitlist_entries_hold_fk', 'hold_id'));
        });
        Schema::table('orders', function (Blueprint $table) use ($key) {
            $table->dropForeign($key('orders_affiliate_fk', 'affiliate_id'));
            $table->dropForeign($key('orders_discount_code_fk', 'discount_code_id'));
            $table->dropForeign($key('orders_issued_by_fk', 'issued_by'));
        });
        Schema::table('discount_redemptions', function (Blueprint $table) use ($key) {
            $table->dropForeign($key('discount_redemptions_order_fk', 'order_id'));
        });
        Schema::table('ticket_transfers', function (Blueprint $table) use ($key) {
            $table->dropUnique('ticket_transfers_token_hash_unique');
            $table->dropForeign($key('ticket_transfers_order_fk', 'order_id'));
            $table->dropForeign($key('ticket_transfers_from_user_fk', 'from_user_id'));
            $table->dropForeign($key('ticket_transfers_to_user_fk', 'to_user_id'));
        });
    }

    /** Every reference about to get a foreign key must point at a row that exists. */
    private function assertNoOrphans(): void
    {
        $references = [
            ['ticket_transfers', 'order_id', 'orders'],
            ['ticket_transfers', 'from_user_id', 'users'],
            ['ticket_transfers', 'to_user_id', 'users'],
            ['discount_redemptions', 'order_id', 'orders'],
            ['orders', 'affiliate_id', 'affiliates'],
            ['orders', 'discount_code_id', 'discount_codes'],
            ['orders', 'issued_by', 'users'],
            ['waitlist_entries', 'hold_id', 'ticket_holds'],
        ];
        $problems = [];
        foreach ($references as [$table, $column, $parent]) {
            if (!Schema::hasTable($table) || !Schema::hasColumn($table, $column) || !Schema::hasTable($parent)) {
                continue;
            }
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

    /** token_hash must be unique before the index can be created; collisions are never merged. */
    private function assertUniqueTransferTokens(): void
    {
        if (!Schema::hasTable('ticket_transfers')) {
            return;
        }
        $duplicates = DB::table('ticket_transfers')
            ->select('token_hash')
            ->groupBy('token_hash')
            ->havingRaw('COUNT(*) > 1')
            ->count();
        if ($duplicates > 0) {
            throw new RuntimeException("Cannot add ticket_transfers.token_hash unique index: {$duplicates} duplicate hash(es) exist. Review the rows; no data was changed.");
        }
    }
};
