<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     *
     * 1. host_applications: Formal Host request and approval workflow for registered Attendees.
     * 2. orders: Explicitly link orders to authenticated user_id for strict ownership and IDOR protection.
     */
    public function up(): void
    {
        // 1. Host Applications Table
        if (!Schema::hasTable('host_applications')) {
            Schema::create('host_applications', function (Blueprint $table) {
                $table->id();
                $table->foreignId('user_id')->constrained('users')->cascadeOnDelete();
                $table->string('organization_name', 255);
                $table->text('description')->nullable();
                $table->string('country', 8)->default('JM'); // JM or US
                $table->string('contact_phone', 32);
                $table->string('website_or_social', 255)->nullable();
                $table->string('status', 32)->default('pending'); // pending, approved, rejected
                $table->foreignId('reviewed_by')->nullable()->constrained('users')->nullOnDelete();
                $table->timestamp('reviewed_at')->nullable();
                $table->text('rejection_reason')->nullable();
                $table->text('review_notes')->nullable();
                $table->timestamps();

                $table->index(['status', 'created_at']);
                $table->index('user_id');
            });
        }

        // 2. Add user_id to orders table if not already present
        if (Schema::hasTable('orders') && !Schema::hasColumn('orders', 'user_id')) {
            Schema::table('orders', function (Blueprint $table) {
                $table->foreignId('user_id')->nullable()->after('event_id')->constrained('users')->nullOnDelete();
                $table->index('user_id');
            });
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        if (Schema::hasTable('orders') && Schema::hasColumn('orders', 'user_id')) {
            Schema::table('orders', function (Blueprint $table) {
                $table->dropForeign(['user_id']);
                $table->dropColumn('user_id');
            });
        }

        Schema::dropIfExists('host_applications');
    }
};
