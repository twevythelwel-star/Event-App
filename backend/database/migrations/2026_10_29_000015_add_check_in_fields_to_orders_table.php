<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Gate scans (EventWorkerService::scanAndValidateTicket) record when a ticket was admitted and by whom,
     * and use checked_in_at to refuse a second entry on the same ticket.
     */
    public function up(): void
    {
        Schema::table('orders', function (Blueprint $table) {
            if (!Schema::hasColumn('orders', 'checked_in_at')) {
                $table->timestamp('checked_in_at')->nullable()->after('closed_at');
            }
            if (!Schema::hasColumn('orders', 'checked_in_by')) {
                $table->foreignId('checked_in_by')->nullable()->after('checked_in_at')->constrained('users')->nullOnDelete();
            }
            $table->index(['event_id', 'checked_in_at']);
        });
    }

    public function down(): void
    {
        Schema::table('orders', function (Blueprint $table) {
            $table->dropIndex(['event_id', 'checked_in_at']);
            $table->dropConstrainedForeignId('checked_in_by');
            $table->dropColumn('checked_in_at');
        });
    }
};
