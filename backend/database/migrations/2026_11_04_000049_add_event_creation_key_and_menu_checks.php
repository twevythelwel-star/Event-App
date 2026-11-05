<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * - events.creation_key: the key the create form (and its autosave) sends with a new event. Unique per organizer,
 *   so a refreshed or retried submission finds the event it already created instead of making a second one.
 * - On MySQL, CHECK constraints so a menu item's stock and price, and an order line's preparation status, stay
 *   valid however they are written. SQLite cannot add constraints to an existing table; the application's row
 *   locks and validation cover development databases, as in 000033.
 *
 * Before a constraint is added the existing rows are checked; the migration stops rather than change data.
 */
return new class extends Migration
{
    private const CHECKS = [
        'menu_items' => [
            'menu_items_stock_nonneg_chk' => 'available_stock IS NULL OR available_stock >= 0',
            'menu_items_price_nonneg_chk' => 'price_minor IS NULL OR price_minor >= 0',
        ],
        'order_items' => [
            'order_items_prep_status_chk' => "preparation_status IN ('pending', 'preparing', 'ready')",
        ],
    ];

    public function up(): void
    {
        Schema::table('events', function (Blueprint $table) {
            $table->uuid('creation_key')->nullable();
            $table->unique(['organizer_id', 'creation_key'], 'events_org_creation_key_unique');
        });

        if (!in_array(DB::getDriverName(), ['mysql', 'mariadb', 'pgsql'], true)) {
            return;
        }
        $problems = [];
        foreach (self::CHECKS as $table => $checks) {
            foreach ($checks as $name => $condition) {
                $count = DB::table($table)->whereRaw("NOT ({$condition})")->count();
                if ($count > 0) {
                    $problems[] = "{$count} row(s) in {$table} fail {$name}";
                }
            }
        }
        if ($problems !== []) {
            throw new RuntimeException('Cannot add menu constraints until these rows are reviewed (no data was changed): ' . implode('; ', $problems) . '.');
        }
        foreach (self::CHECKS as $table => $checks) {
            foreach ($checks as $name => $condition) {
                DB::statement("ALTER TABLE {$table} ADD CONSTRAINT {$name} CHECK ({$condition})");
            }
        }
    }

    public function down(): void
    {
        if (in_array(DB::getDriverName(), ['mysql', 'mariadb', 'pgsql'], true)) {
            $drop = DB::getDriverName() === 'pgsql' ? 'DROP CONSTRAINT' : 'DROP CHECK';
            foreach (self::CHECKS as $table => $checks) {
                foreach (array_keys($checks) as $name) {
                    DB::statement("ALTER TABLE {$table} {$drop} {$name}");
                }
            }
        }
        Schema::table('events', function (Blueprint $table) {
            $table->dropUnique('events_org_creation_key_unique');
            $table->dropColumn('creation_key');
        });
    }
};
