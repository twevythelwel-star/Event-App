<?php

use App\Services\LegalComplianceService;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Version-controlled policies.
 *
 * Every policy version gets its category, effective date, applicable roles and jurisdictions, and whether
 * acknowledgement is mandatory and whether it must be acknowledged again. The built-in policies' text is stored as
 * their first version, so the database holds the exact wording people accepted. Each acknowledgement record points at
 * the exact version accepted.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('compliance_policy_versions', function (Blueprint $table) {
            $table->string('category', 40)->default('other');
            $table->json('applicable_roles')->nullable();
            $table->json('applicable_jurisdictions')->nullable(); // state codes; empty means everywhere
            $table->boolean('acknowledgement_mandatory')->default(true);
            $table->boolean('requires_reacknowledgement')->default(true);
            $table->timestamp('effective_at')->nullable();
            $table->string('source', 20)->default('admin'); // built_in (the original text) | admin
            $table->unsignedBigInteger('drafted_by')->nullable()->change();
        });

        Schema::table('policy_acceptances', function (Blueprint $table) {
            $table->unsignedBigInteger('policy_version_id')->nullable()->index();
        });

        // The built-in policies become version 1 of each policy (never edited afterwards; new text is a new version).
        $published = '2026-11-06 00:00:00';
        $rows = [];
        $add = function (string $name, string $title, string $body, string $category, array $roles) use (&$rows, $published) {
            $rows[] = [
                'policy_name' => $name, 'policy_group' => $roles === ['event_host'] ? 'host' : 'account',
                'version' => LegalComplianceService::CURRENT_POLICY_VERSION, 'title' => $title, 'body' => $body,
                'change_summary' => 'Original version.', 'status' => 'published', 'drafted_by' => null, 'published_by' => null,
                'published_at' => $published, 'effective_at' => $published, 'category' => $category, 'applicable_roles' => json_encode($roles),
                'applicable_jurisdictions' => json_encode([]), 'acknowledgement_mandatory' => true, 'requires_reacknowledgement' => true,
                'source' => 'built_in', 'created_at' => now(), 'updated_at' => now(),
            ];
        };
        $categories = ['platform_terms_and_conditions' => 'terms_of_service', 'food_safety_disclaimer' => 'food_safety', 'alcohol_policy' => 'alcohol'];
        foreach (LegalComplianceService::builtInRegistrationPolicies() as $spec) {
            $add($spec['policy_name'], $spec['title'], implode("\n", $spec['clauses']), $categories[$spec['policy_name']] ?? 'other', ['all']);
        }
        foreach (LegalComplianceService::builtInHostResponsibilityPolicies() as $spec) {
            $add($spec['policy_name'], $spec['title'], $spec['description'], 'host_responsibility', ['event_host']);
        }
        $declarations = LegalComplianceService::eventComplianceDeclarations();
        foreach (['food_safety' => 'event_food_safety_and_sanitation', 'alcohol' => 'event_alcohol_service_and_age_verification',
            'general_legal' => 'event_general_legal_responsibility'] as $group => $name) {
            $add($name, 'Event Compliance: ' . $declarations[$group]['title'], implode("\n", $declarations[$group]['items']), 'event_compliance', ['event_host']);
        }
        foreach ($rows as $row) {
            $exists = DB::table('compliance_policy_versions')->where('policy_name', $row['policy_name'])->where('version', $row['version'])->exists();
            if (!$exists) {
                DB::table('compliance_policy_versions')->insert($row);
            }
        }

        // Point existing acknowledgement records at the version they accepted.
        foreach (DB::table('compliance_policy_versions')->get(['id', 'policy_name', 'version']) as $version) {
            DB::table('policy_acceptances')->where('policy_name', $version->policy_name)->where('policy_version', $version->version)
                ->whereNull('policy_version_id')->update(['policy_version_id' => $version->id]);
        }
    }

    public function down(): void
    {
        DB::table('compliance_policy_versions')->where('source', 'built_in')->delete();
        Schema::table('policy_acceptances', function (Blueprint $table) {
            $table->dropIndex(['policy_version_id']); // SQLite cannot drop an indexed column
            $table->dropColumn('policy_version_id');
        });
        Schema::table('compliance_policy_versions', fn (Blueprint $table) => $table->dropColumn([
            'category', 'applicable_roles', 'applicable_jurisdictions', 'acknowledgement_mandatory', 'requires_reacknowledgement', 'effective_at', 'source',
        ]));
    }
};
