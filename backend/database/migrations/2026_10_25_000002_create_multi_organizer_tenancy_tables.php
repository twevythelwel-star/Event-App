<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * Run the migrations.
     *
     * Creates the core tenancy infrastructure:
     * 1. organizers (Tenants)
     * 2. organizer_users (RBAC pivot with TenantRole)
     * 3. organizer_id on notification_logs
     * Tenant-scoped events, orders, menu_items and cash_payments are created in the core commerce migration.
     */
    public function up(): void
    {
        // 1. Organizers (Tenants) table
        Schema::create('organizers', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->string('name');
            $table->string('slug')->unique(); // For custom subdomain/vanity URL: e.g. kingstonyouth.events.app
            $table->string('status')->default('active'); // active, suspended, pending_onboarding
            $table->string('default_currency', 3)->default('JMD');
            $table->string('business_location', 2)->default('JM'); // JM or US
            $table->string('flyer_storage_prefix')->nullable(); // e.g. /tenants/{organizer_id}/flyers/
            $table->json('payment_settings')->nullable(); // Zelle, Cash App, Jamaican bank details (encrypted/scoped)
            $table->json('branding')->nullable(); // Primary colors, logo URL, contact email/phone
            $table->timestamps();
            $table->softDeletes();

            $table->index('status');
            $table->index('slug');
        });

        // 2. Organizer Users (RBAC Pivot)
        Schema::create('organizer_users', function (Blueprint $table) {
            $table->id();
            $table->foreignUuid('organizer_id')->constrained('organizers')->cascadeOnDelete();
            $table->foreignId('user_id')->constrained('users')->cascadeOnDelete();
            $table->string('role')->default('food_collector'); // platform_admin, event_owner, payment_verifier, food_collector
            $table->string('station_assignment')->default('all'); // all, mains, sides, drinks, desserts
            $table->boolean('is_active')->default(true);
            $table->json('custom_permissions')->nullable(); // Optional fine-grained overrides
            $table->timestamp('last_active_at')->nullable();
            $table->timestamps();

            // Prevent duplicate memberships & ensure rapid tenant-user lookup
            $table->unique(['organizer_id', 'user_id']);
            $table->index(['user_id', 'role']);
            $table->index(['organizer_id', 'role']);
        });

        // 3. Ensure notification_logs has organizer_id with composite index
        if (Schema::hasTable('notification_logs')) {
            Schema::table('notification_logs', function (Blueprint $table) {
                if (!Schema::hasColumn('notification_logs', 'organizer_id')) {
                    $table->foreignUuid('organizer_id')->nullable()->after('id')->index();
                }
            });
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('organizer_users');
        Schema::dropIfExists('organizers');
    }
};
