<?php

declare(strict_types=1);

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
        if (!Schema::hasTable('event_worker_assignments')) {
            Schema::create('event_worker_assignments', function (Blueprint $table) {
                $table->id();
                $table->foreignUuid('organizer_id')->constrained('organizers')->cascadeOnDelete();
                $table->foreignUuid('event_id')->nullable()->constrained('events')->cascadeOnDelete();
                $table->foreignId('user_id')->constrained('users')->cascadeOnDelete();
                $table->string('worker_role'); // payment_clerk, ticket_checker, food_station_worker, event_supervisor, custom_restricted_worker
                $table->string('station_assignment')->default('all'); // all, mains, sides, drinks, desserts, station_1, etc.
                $table->json('custom_permissions')->nullable(); // Whitelisted permissions for custom_restricted_worker
                $table->foreignId('assigned_by')->nullable()->constrained('users')->nullOnDelete();
                $table->boolean('is_active')->default(true);
                $table->timestamp('last_active_at')->nullable();
                $table->timestamps();

                // Efficient indexes for role checks & event operations
                $table->index(['event_id', 'worker_role', 'is_active'], 'idx_event_worker_role');
                $table->index(['user_id', 'is_active'], 'idx_user_worker_active');
                $table->index(['organizer_id', 'is_active'], 'idx_org_worker_active');
            });
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('event_worker_assignments');
    }
};
