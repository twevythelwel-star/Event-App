<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Compliance schema hardening.
 *
 *  - compliance_policies: one row per policy (its unique identifier and category); every version belongs to one.
 *  - Foreign keys on the compliance tables added since migration 000061, so suspensions, safety reports, policy
 *    versions and acknowledgement records cannot point at people, events or versions that do not exist.
 *  - Indexes for the queries the dashboards and gates run on every page.
 *  - Soft deletion of permit and licence submissions: a host may withdraw a submission before it is reviewed, and
 *    the withdrawn record is kept for the audit trail rather than erased.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('compliance_policies', function (Blueprint $table) {
            $table->id();
            $table->string('policy_name', 120)->unique();
            $table->string('category', 40)->index();
            $table->timestamps();
        });
        $now = now();
        foreach (DB::table('compliance_policy_versions')->orderBy('id')->get(['policy_name', 'category']) as $row) {
            DB::table('compliance_policies')->insertOrIgnore(['policy_name' => $row->policy_name, 'category' => $row->category ?? 'other', 'created_at' => $now, 'updated_at' => $now]);
        }

        Schema::table('compliance_policy_versions', function (Blueprint $table) {
            $table->foreignId('policy_id')->nullable()->after('id')->constrained('compliance_policies')->restrictOnDelete();
            $table->foreign('drafted_by')->references('id')->on('users')->nullOnDelete();
            $table->foreign('published_by')->references('id')->on('users')->nullOnDelete();
            $table->index(['status', 'effective_at']);
        });
        foreach (DB::table('compliance_policies')->get(['id', 'policy_name']) as $policy) {
            DB::table('compliance_policy_versions')->where('policy_name', $policy->policy_name)->update(['policy_id' => $policy->id]);
        }

        Schema::table('policy_acceptances', function (Blueprint $table) {
            // Acknowledgement records are kept: a version or a person they belong to cannot be hard-deleted from under them.
            $table->foreign('policy_version_id')->references('id')->on('compliance_policy_versions')->restrictOnDelete();
            $table->foreign('user_id')->references('id')->on('users')->restrictOnDelete();
        });

        Schema::table('host_publishing_suspensions', function (Blueprint $table) {
            $table->foreign('user_id')->references('id')->on('users')->cascadeOnDelete();
            $table->foreign('suspended_by')->references('id')->on('users');
            $table->foreign('lifted_by')->references('id')->on('users')->nullOnDelete();
            $table->index(['user_id', 'lifted_at']);
        });

        Schema::table('compliance_safety_reports', function (Blueprint $table) {
            $table->foreign('event_id')->references('id')->on('events')->nullOnDelete();
            $table->foreign('reported_by')->references('id')->on('users')->nullOnDelete();
            $table->foreign('assigned_to')->references('id')->on('users')->nullOnDelete();
            $table->foreign('resolved_by')->references('id')->on('users')->nullOnDelete();
            $table->index(['status', 'priority']);
        });

        Schema::table('admin_permissions', function (Blueprint $table) {
            $table->foreign('identity_access_granted_by')->references('id')->on('users')->nullOnDelete();
        });

        Schema::table('event_compliance_evidence', function (Blueprint $table) {
            $table->softDeletes();
            $table->index(['status', 'expires_on']);
        });

        Schema::table('events', function (Blueprint $table) {
            $table->index('compliance_suspended_at');
        });
    }

    public function down(): void
    {
        Schema::table('events', fn (Blueprint $table) => $table->dropIndex(['compliance_suspended_at']));
        Schema::table('event_compliance_evidence', function (Blueprint $table) {
            $table->dropIndex(['status', 'expires_on']);
            $table->dropSoftDeletes();
        });
        Schema::table('admin_permissions', fn (Blueprint $table) => $table->dropForeign(['identity_access_granted_by']));
        Schema::table('compliance_safety_reports', function (Blueprint $table) {
            $table->dropForeign(['event_id']);
            $table->dropForeign(['reported_by']);
            $table->dropForeign(['assigned_to']);
            $table->dropForeign(['resolved_by']);
            $table->dropIndex(['status', 'priority']);
        });
        Schema::table('host_publishing_suspensions', function (Blueprint $table) {
            $table->dropForeign(['user_id']);
            $table->dropForeign(['suspended_by']);
            $table->dropForeign(['lifted_by']);
            $table->dropIndex(['user_id', 'lifted_at']);
        });
        Schema::table('policy_acceptances', function (Blueprint $table) {
            $table->dropForeign(['policy_version_id']);
            $table->dropForeign(['user_id']);
        });
        Schema::table('compliance_policy_versions', function (Blueprint $table) {
            $table->dropForeign(['drafted_by']);
            $table->dropForeign(['published_by']);
            $table->dropIndex(['status', 'effective_at']);
            $table->dropConstrainedForeignId('policy_id');
        });
        Schema::dropIfExists('compliance_policies');
    }
};
