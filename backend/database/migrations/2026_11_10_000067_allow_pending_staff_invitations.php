<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * A staff invitation sent to an email address (EventWorkerService::createWorkerInvitation) has no user until the
 * person accepts it, so event_worker_assignments.user_id must allow null while the invitation is pending. The
 * foreign key to users stays; accepting the invitation sets the user.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('event_worker_assignments', function (Blueprint $table) {
            $table->unsignedBigInteger('user_id')->nullable()->change();
        });
    }

    public function down(): void
    {
        // Pending invitations without a user cannot survive the stricter column; they are removed first.
        DB::table('event_worker_assignments')->whereNull('user_id')->delete();
        Schema::table('event_worker_assignments', function (Blueprint $table) {
            $table->unsignedBigInteger('user_id')->nullable(false)->change();
        });
    }
};
