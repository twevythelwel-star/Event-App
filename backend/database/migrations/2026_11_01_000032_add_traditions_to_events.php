<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * The traditions and cuisines a host says an event celebrates or serves (keys from config/traditions.php).
 * Null means the host has not chosen yet; discovery then suggests traditions from the event's own text.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('events', function (Blueprint $table) {
            $table->json('traditions')->nullable();
        });
    }

    public function down(): void
    {
        Schema::table('events', function (Blueprint $table) {
            $table->dropColumn('traditions');
        });
    }
};
