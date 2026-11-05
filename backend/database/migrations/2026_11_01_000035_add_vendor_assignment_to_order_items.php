<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('order_items', function (Blueprint $table) {
            // Vendor or food-station assignment for fulfillment tracking
            $table->string('vendor_assignment', 100)->nullable()->after('special_instructions');

            // Index for filtering by vendor/station
            $table->index('vendor_assignment');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('order_items', function (Blueprint $table) {
            $table->dropIndex(['vendor_assignment']);
            $table->dropColumn('vendor_assignment');
        });
    }
};
