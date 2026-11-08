<?php

use App\Support\LegalPages;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * The user-facing legal pages' policies that are new (host agreement, identity privacy notice, cancellation and
 * refund policy, attendee safety notice, privacy policy) become version 1 of each policy. They are notices, so
 * acknowledgement is not mandatory; making one mandatory is done by publishing a new version that says so.
 */
return new class extends Migration
{
    public function up(): void
    {
        $now = now();
        foreach (LegalPages::originals() as $name => $policy) {
            if (DB::table('compliance_policy_versions')->where('policy_name', $name)->exists()) {
                continue;
            }
            DB::table('compliance_policy_versions')->insert([
                'policy_name' => $name, 'policy_group' => $policy['roles'] === ['event_host'] ? 'host' : 'account',
                'version' => LegalPages::ORIGINAL_VERSION, 'title' => $policy['title'], 'body' => $policy['body'],
                'change_summary' => 'Original version (draft wording pending review by legal counsel).', 'status' => 'published',
                'drafted_by' => null, 'published_by' => null, 'published_at' => $now, 'effective_at' => $now,
                'category' => $policy['category'], 'applicable_roles' => json_encode($policy['roles']), 'applicable_jurisdictions' => json_encode([]),
                'acknowledgement_mandatory' => false, 'requires_reacknowledgement' => false, 'source' => 'built_in',
                'created_at' => $now, 'updated_at' => $now,
            ]);
        }
    }

    public function down(): void
    {
        DB::table('compliance_policy_versions')->whereIn('policy_name', array_keys(LegalPages::originals()))->where('source', 'built_in')->delete();
    }
};
