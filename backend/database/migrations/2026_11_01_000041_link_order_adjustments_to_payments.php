<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::table('order_adjustments', function (Blueprint $table) {
            $table->foreignUuid('payment_id')->nullable()->after('order_id')->constrained('payments')->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('order_adjustments', function (Blueprint $table) {
            $table->dropConstrainedForeignId('payment_id');
        });
    }
};
