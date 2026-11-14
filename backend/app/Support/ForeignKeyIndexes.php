<?php

namespace App\Support;

use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * For migration down() methods that drop an index on MySQL.
 *
 * MySQL keeps one index behind every foreign key. When a migration adds an index that starts with a foreign key's
 * column, MySQL drops the plain index it made for the key and uses the new one instead; it then refuses to drop
 * the new index (error 1553, "needed in a foreign key constraint"). Rolling such a migration back would fail.
 */
final class ForeignKeyIndexes
{
    /**
     * Call before dropping $index (its name, or its columns as Laravel's dropIndex takes them). On MySQL, when a
     * foreign key on the index's first column would be left without an index, gives the key back the plain index it
     * had before. Other drivers, and indexes no foreign key depends on, are left alone.
     *
     * @param  string|list<string>  $index
     */
    public static function keepFor(string $table, string|array $index): void
    {
        if (!in_array(DB::getDriverName(), ['mysql', 'mariadb'], true) || !Schema::hasTable($table)) {
            return;
        }

        $indexes = collect(Schema::getIndexes($table));
        $dropping = is_array($index)
            ? $indexes->first(fn (array $i) => $i['columns'] === array_values($index))
            : $indexes->firstWhere('name', $index);
        if ($dropping === null || count($dropping['columns']) < 2) {
            return; // Not there, or a single-column index: down() drops its foreign key first, as it must anywhere.
        }

        $column = $dropping['columns'][0];
        $hasForeignKey = collect(Schema::getForeignKeys($table))->contains(fn (array $fk) => ($fk['columns'][0] ?? null) === $column);
        $otherIndex = $indexes->contains(fn (array $i) => $i['name'] !== $dropping['name'] && ($i['columns'][0] ?? null) === $column);

        if ($hasForeignKey && !$otherIndex) {
            Schema::table($table, fn (Blueprint $t) => $t->index([$column]));
        }
    }
}
