<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('events', function (Blueprint $table) {
            $table->dropForeign(['state_code']);
            $table->string('state_code', 80)->nullable()->change();
        });
    }

    public function down(): void
    {
        DB::table('events')
            ->where('country_code', '!=', 'US')
            ->orWhereNull('country_code')
            ->update(['state_code' => null]);

        Schema::table('events', function (Blueprint $table) {
            $table->char('state_code', 2)->nullable()->change();
            $table->foreign('state_code')->references('code')->on('us_states')->nullOnDelete();
        });
    }
};
