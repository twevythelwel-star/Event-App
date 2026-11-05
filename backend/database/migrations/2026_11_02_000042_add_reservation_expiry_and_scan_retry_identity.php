<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('orders', function (Blueprint $table): void {
            if (!Schema::hasColumn('orders', 'reservation_expires_at')) {
                $table->timestamp('reservation_expires_at')->nullable();
                $table->index(['status', 'reservation_expires_at'], 'orders_reservation_expiry_idx');
            }
            if (!Schema::hasColumn('orders', 'inventory_released_at')) {
                $table->timestamp('inventory_released_at')->nullable();
            }
            if (!Schema::hasColumn('orders', 'check_in_request_id')) {
                $table->uuid('check_in_request_id')->nullable()->unique('orders_checkin_request_unique');
            }
        });

        Schema::table('payments', function (Blueprint $table): void {
            if (!Schema::hasColumn('payments', 'reconciliation_status')) {
                $table->string('reconciliation_status', 24)->default('unverified');
            }
            if (!Schema::hasColumn('payments', 'last_reconciled_at')) {
                $table->timestamp('last_reconciled_at')->nullable();
            }
            if (!Schema::hasColumn('payments', 'reconciliation_note')) {
                $table->string('reconciliation_note', 255)->nullable();
            }
            if (!Schema::hasIndex('payments', 'payments_reconcile_due_idx')) {
                $table->index(['provider', 'reconciliation_status', 'last_reconciled_at'], 'payments_reconcile_due_idx');
            }
        });
    }

    public function down(): void
    {
        Schema::table('orders', function (Blueprint $table): void {
            if (Schema::hasColumn('orders', 'check_in_request_id')) {
                $table->dropUnique('orders_checkin_request_unique');
                $table->dropColumn('check_in_request_id');
            }
            if (Schema::hasColumn('orders', 'inventory_released_at')) {
                $table->dropColumn('inventory_released_at');
            }
            if (Schema::hasColumn('orders', 'reservation_expires_at')) {
                $table->dropIndex('orders_reservation_expiry_idx');
                $table->dropColumn('reservation_expires_at');
            }
        });
        Schema::table('payments', function (Blueprint $table): void {
            if (Schema::hasColumn('payments', 'reconciliation_status')) {
                $table->dropIndex('payments_reconcile_due_idx');
                $table->dropColumn('reconciliation_status');
            }
            if (Schema::hasColumn('payments', 'last_reconciled_at')) $table->dropColumn('last_reconciled_at');
            if (Schema::hasColumn('payments', 'reconciliation_note')) $table->dropColumn('reconciliation_note');
        });
    }
};
