<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     *
     * Creates:
     * (users is created by 0001_01_01_000000_create_users_table)
     * 2. user_roles: Explicit role assignments (system_admin, admin, event_host, attendee)
     * 3. admin_permissions: Scoped permissions for Delegated Administrators
     * 4. account_settings: Structured settings per user/organizer with sensitive masking
     * 5. security_audit_logs: Immutable audit trail for privileged operations and settings changes
     * 6. host_event_settings: Event Host defaults (currency, timezone, location, deadline, branding)
     * 7. host_payment_policies: Host payment instructions, cash toggle, and eligibility
     * 8. host_operational_rules: Staff stations, prep buffers, and redemption rules
     * 9. system_platform_settings: Platform branding, supported rails, health monitors, retention
     */
    public function up(): void
    {
        // 2. User Roles Table (Many-to-Many / Explicit Role Assignments)
        if (!Schema::hasTable('user_roles')) {
            Schema::create('user_roles', function (Blueprint $table) {
                $table->id();
                $table->foreignId('user_id')->constrained('users')->cascadeOnDelete();
                $table->string('role', 32); // system_admin, admin, event_host, attendee
                $table->boolean('is_active')->default(true);
                $table->foreignId('assigned_by')->nullable()->constrained('users')->nullOnDelete();
                $table->timestamp('assigned_at')->useCurrent();
                $table->timestamps();

                $table->unique(['user_id', 'role']);
                $table->index(['user_id', 'is_active']);
            });
        }

        // 3. Admin Permissions Table (Delegated Administrator Scopes)
        if (!Schema::hasTable('admin_permissions')) {
            Schema::create('admin_permissions', function (Blueprint $table) {
                $table->id();
                $table->foreignId('user_id')->constrained('users')->cascadeOnDelete();
                $table->string('scope', 64)->default('assigned_events'); // all, assigned_events, dispute_support
                $table->boolean('can_manage_events')->default(false);
                $table->boolean('can_moderate_content')->default(false);
                $table->boolean('can_support_orders')->default(true);
                $table->boolean('can_view_payments')->default(false);
                $table->boolean('can_view_reports')->default(false);
                $table->boolean('can_access_audit_logs')->default(false);
                $table->json('assigned_organizer_ids')->nullable();
                $table->timestamps();

                $table->unique('user_id');
            });
        }

        // 4. Account Settings Table (Categorized & Scoped Key/Value Store)
        if (!Schema::hasTable('account_settings')) {
            Schema::create('account_settings', function (Blueprint $table) {
                $table->id();
                $table->foreignId('user_id')->constrained('users')->cascadeOnDelete();
                $table->string('category', 32); // profile, security, notifications, preferences
                $table->string('setting_key', 64);
                $table->text('setting_value')->nullable();
                $table->boolean('is_sensitive')->default(false);
                $table->timestamps();

                $table->unique(['user_id', 'setting_key']);
                $table->index(['user_id', 'category']);
            });
        }

        // 5. Security Audit Logs Table
        if (!Schema::hasTable('security_audit_logs')) {
            Schema::create('security_audit_logs', function (Blueprint $table) {
                $table->uuid('id')->primary();
                $table->foreignId('user_id')->nullable()->constrained('users')->nullOnDelete();
                $table->string('actor_role', 32);
                $table->string('action', 64);
                $table->string('target_entity', 64);
                $table->string('target_id', 64)->nullable();
                $table->string('ip_address', 45)->nullable();
                $table->text('user_agent')->nullable();
                $table->text('details');
                $table->boolean('is_pii_access')->default(false);
                $table->timestamp('created_at')->useCurrent()->index();

                $table->index(['actor_role', 'action']);
                $table->index(['target_entity', 'target_id']);
            });
        }

        // 6. Host Event Settings Table (Dedicated Event Host Defaults)
        if (!Schema::hasTable('host_event_settings')) {
            Schema::create('host_event_settings', function (Blueprint $table) {
                $table->uuid('id')->primary();
                $table->foreignUuid('organizer_id')->constrained('organizers')->cascadeOnDelete();
                $table->string('default_currency', 4)->default('JMD');
                $table->string('default_timezone', 64)->default('America/Jamaica');
                $table->text('default_location')->nullable();
                $table->unsignedInteger('default_order_deadline_hours')->default(24);
                $table->text('default_pickup_instructions')->nullable();
                $table->string('default_flyer_aspect_ratio', 16)->default('4:5');
                $table->string('default_brand_theme', 64)->default('caribbean_emerald');
                $table->timestamps();

                $table->unique('organizer_id');
            });
        }

        // 7. Host Payment Policies Table
        if (!Schema::hasTable('host_payment_policies')) {
            Schema::create('host_payment_policies', function (Blueprint $table) {
                $table->uuid('id')->primary();
                $table->foreignUuid('organizer_id')->constrained('organizers')->cascadeOnDelete();
                $table->boolean('enable_cash')->default(true);
                $table->text('zelle_instructions')->nullable();
                $table->string('cash_app_instructions', 128)->nullable();
                $table->text('bank_transfer_instructions')->nullable();
                $table->boolean('require_transfer_reference')->default(true);
                $table->boolean('require_sender_name')->default(true);
                $table->boolean('require_receipt_snapshot')->default(true);
                $table->boolean('partial_payments_enabled')->default(false);
                $table->unsignedInteger('deposit_percentage')->default(50);
                $table->boolean('auto_email_receipt')->default(true);
                $table->boolean('auto_sms_receipt')->default(true);
                $table->unsignedInteger('refund_policy_deadline_hours')->default(48);
                $table->text('refund_policy_notes')->nullable();
                $table->timestamps();

                $table->unique('organizer_id');
            });
        }

        // 8. Host Operational Rules Table
        if (!Schema::hasTable('host_operational_rules')) {
            Schema::create('host_operational_rules', function (Blueprint $table) {
                $table->uuid('id')->primary();
                $table->foreignUuid('organizer_id')->constrained('organizers')->cascadeOnDelete();
                $table->json('stations')->nullable();
                $table->unsignedInteger('max_event_capacity')->default(500);
                $table->unsignedInteger('max_per_item_portions')->default(10);
                $table->boolean('enforce_anti_duplicate_redemption')->default(true);
                $table->boolean('allow_partial_collection')->default(true);
                $table->unsignedInteger('food_prep_buffer_percent')->default(10);
                $table->timestamps();

                $table->unique('organizer_id');
            });
        }

        // 9. System Platform Settings Table
        if (!Schema::hasTable('system_platform_settings')) {
            Schema::create('system_platform_settings', function (Blueprint $table) {
                $table->id();
                $table->string('platform_name')->default('Island Events & Ticketing');
                $table->string('logo_url')->default('/assets/island-events-logo.svg');
                $table->string('brand_theme', 64)->default('caribbean_emerald');
                $table->json('supported_currencies');
                $table->json('supported_payment_methods');
                $table->string('file_storage_status', 32)->default('healthy');
                $table->string('storage_bucket')->default('island-events-flyers-prod');
                $table->string('storage_provider', 64)->default('s3_compatible_r2');
                $table->string('notification_provider_status', 32)->default('operational');
                $table->boolean('mandatory_2fa_for_admins')->default(true);
                $table->unsignedInteger('admin_session_timeout_minutes')->default(30);
                $table->unsignedInteger('attendee_session_timeout_days')->default(30);
                $table->string('default_timezone', 64)->default('America/Jamaica');
                $table->string('default_locale', 16)->default('en-JM');
                $table->unsignedInteger('audit_retention_days')->default(365);
                $table->unsignedInteger('pii_scrub_days')->default(90);
                $table->unsignedInteger('database_pool_max')->default(100);
                $table->unsignedInteger('queue_workers_count')->default(4);
                $table->timestamps();
            });
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('system_platform_settings');
        Schema::dropIfExists('host_operational_rules');
        Schema::dropIfExists('host_payment_policies');
        Schema::dropIfExists('host_event_settings');
        Schema::dropIfExists('security_audit_logs');
        Schema::dropIfExists('account_settings');
        Schema::dropIfExists('admin_permissions');
        Schema::dropIfExists('user_roles');
    }
};
