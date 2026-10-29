<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * The admin console shows each audit record's outcome and the organizer/event it concerned,
     * so scoped administrators only see activity inside their assigned organizers.
     */
    public function up(): void
    {
        Schema::table('security_audit_logs', function (Blueprint $table) {
            if (!Schema::hasColumn('security_audit_logs', 'outcome')) {
                $table->string('outcome', 16)->default('success')->after('details'); // success, denied, failed
            }
            if (!Schema::hasColumn('security_audit_logs', 'organizer_id')) {
                $table->string('organizer_id', 36)->nullable()->after('outcome')->index();
            }
            if (!Schema::hasColumn('security_audit_logs', 'event_id')) {
                $table->string('event_id', 36)->nullable()->after('organizer_id')->index();
            }
        });
    }

    public function down(): void
    {
        Schema::table('security_audit_logs', function (Blueprint $table) {
            $table->dropColumn(['outcome', 'organizer_id', 'event_id']);
        });
    }
};
