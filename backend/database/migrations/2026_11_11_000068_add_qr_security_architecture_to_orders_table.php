<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('orders', function (Blueprint $table) {
            if (!Schema::hasColumn('orders', 'secure_ticket_token_hash')) {
                $table->string('secure_ticket_token_hash', 64)->nullable()->unique()->after('secure_ticket_token');
            }
            if (!Schema::hasColumn('orders', 'token_version')) {
                $table->unsignedInteger('token_version')->default(1)->after('secure_ticket_token_hash');
            }
            if (!Schema::hasColumn('orders', 'token_reissued_at')) {
                $table->timestamp('token_reissued_at')->nullable()->after('token_version');
            }
            if (!Schema::hasColumn('orders', 'token_revoked_at')) {
                $table->timestamp('token_revoked_at')->nullable()->after('token_reissued_at');
            }
            if (!Schema::hasColumn('orders', 'revocation_reason')) {
                $table->string('revocation_reason', 255)->nullable()->after('token_revoked_at');
            }
        });

        // Backfill SHA-256 token hashes for all existing orders
        DB::table('orders')
            ->whereNotNull('secure_ticket_token')
            ->whereNull('secure_ticket_token_hash')
            ->orderBy('id')
            ->chunk(200, function ($rows): void {
                foreach ($rows as $row) {
                    $token = trim((string) $row->secure_ticket_token);
                    if ($token !== '') {
                        DB::table('orders')
                            ->where('id', $row->id)
                            ->update([
                                'secure_ticket_token_hash' => hash('sha256', $token),
                            ]);
                    }
                }
            });
    }

    public function down(): void
    {
        // SQLite cannot drop a column that still has an index on it: drop the unique index first.
        if (Schema::hasColumn('orders', 'secure_ticket_token_hash')) {
            Schema::table('orders', function (Blueprint $table) {
                $table->dropUnique('orders_secure_ticket_token_hash_unique');
            });
        }

        Schema::table('orders', function (Blueprint $table) {
            $drop = array_filter(
                ['secure_ticket_token_hash', 'token_version', 'token_reissued_at', 'token_revoked_at', 'revocation_reason'],
                fn (string $col) => Schema::hasColumn('orders', $col)
            );
            if ($drop !== []) {
                $table->dropColumn($drop);
            }
        });
    }
};

