<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * The administrators' compliance management dashboard:
 *  - two explicit administrator grants: compliance management, and access to host identity documents;
 *  - suspending an event or a host's publishing privileges on compliance grounds;
 *  - safety issues reported about events;
 *  - versioned, published policies and acknowledgements.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('admin_permissions', function (Blueprint $table) {
            $table->boolean('can_manage_compliance')->default(false);
            // Identity documents are opened only by administrators specifically granted this, never by role alone.
            $table->boolean('can_access_identity_documents')->default(false);
            $table->unsignedBigInteger('identity_access_granted_by')->nullable();
            $table->timestamp('identity_access_granted_at')->nullable();
        });

        Schema::table('events', function (Blueprint $table) {
            $table->timestamp('compliance_suspended_at')->nullable();
            $table->unsignedBigInteger('compliance_suspended_by')->nullable();
            $table->text('compliance_suspension_reason')->nullable();
        });

        Schema::create('host_publishing_suspensions', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('user_id')->index();
            $table->text('reason');
            $table->unsignedBigInteger('suspended_by');
            $table->timestamp('suspended_at');
            $table->date('review_by')->nullable();
            $table->timestamp('lifted_at')->nullable();
            $table->unsignedBigInteger('lifted_by')->nullable();
            $table->text('lift_reason')->nullable();
            $table->timestamps();
        });

        Schema::create('compliance_safety_reports', function (Blueprint $table) {
            $table->id();
            $table->string('event_id')->nullable()->index();
            $table->unsignedBigInteger('reported_by')->nullable();
            $table->string('category', 40);
            $table->text('description');
            $table->date('occurred_on')->nullable();
            $table->boolean('may_contact')->default(false);
            $table->string('status', 20)->default('open')->index();
            $table->string('priority', 10)->default('normal');
            $table->unsignedBigInteger('assigned_to')->nullable();
            $table->text('resolution')->nullable();
            $table->unsignedBigInteger('resolved_by')->nullable();
            $table->timestamp('resolved_at')->nullable();
            $table->timestamps();
        });

        Schema::create('compliance_policy_versions', function (Blueprint $table) {
            $table->id();
            $table->string('policy_name', 120);
            $table->string('policy_group', 20); // account | host
            $table->string('version', 40);
            $table->string('title');
            $table->text('body');
            $table->text('change_summary')->nullable();
            $table->string('status', 20)->default('draft'); // draft | published | retired
            $table->unsignedBigInteger('drafted_by');
            $table->unsignedBigInteger('published_by')->nullable();
            $table->timestamp('published_at')->nullable();
            $table->timestamps();
            $table->unique(['policy_name', 'version']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('compliance_policy_versions');
        Schema::dropIfExists('compliance_safety_reports');
        Schema::dropIfExists('host_publishing_suspensions');
        Schema::table('events', fn (Blueprint $table) => $table->dropColumn(['compliance_suspended_at', 'compliance_suspended_by', 'compliance_suspension_reason']));
        Schema::table('admin_permissions', fn (Blueprint $table) => $table->dropColumn(['can_manage_compliance', 'can_access_identity_documents', 'identity_access_granted_by', 'identity_access_granted_at']));
    }
};
