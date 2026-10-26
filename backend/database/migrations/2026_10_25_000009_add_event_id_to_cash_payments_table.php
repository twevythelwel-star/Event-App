<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Ties every cash ledger row to an event, so walk-up cash (no order) is scoped like order cash
     * and event-assigned cashiers can see the ledger for their event only.
     */
    public function up(): void
    {
        Schema::table('cash_payments', function (Blueprint $table) {
            $table->foreignUuid('event_id')->nullable()->after('organizer_id')->constrained('events')->nullOnDelete();
            $table->index(['organizer_id', 'event_id']);
        });
    }

    public function down(): void
    {
        Schema::table('cash_payments', function (Blueprint $table) {
            $table->dropIndex(['organizer_id', 'event_id']);
            $table->dropConstrainedForeignId('event_id');
        });
    }
};
