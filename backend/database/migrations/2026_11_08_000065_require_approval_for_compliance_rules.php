<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Event checklist rules decide what hosts are told is required. A new rule now takes effect only after a system
 * administrator other than its author approves it, the same two-person check as compliance database records.
 * Who approved a rule, and when, is recorded.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('compliance_event_rules', function (Blueprint $table) {
            $table->foreignId('approved_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('approved_at')->nullable();
        });
    }

    public function down(): void
    {
        Schema::table('compliance_event_rules', function (Blueprint $table) {
            $table->dropConstrainedForeignId('approved_by');
            $table->dropColumn('approved_at');
        });
    }
};
