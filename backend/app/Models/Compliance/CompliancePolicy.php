<?php

declare(strict_types=1);

namespace App\Models\Compliance;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * A policy: its unique identifier (policy_name) and category. Its text lives in its versions
 * (CompliancePolicyVersion), which are never overwritten; PolicyCatalog decides which version is in force.
 */
class CompliancePolicy extends Model
{
    protected $fillable = ['policy_name', 'category'];

    public function versions(): HasMany
    {
        return $this->hasMany(CompliancePolicyVersion::class, 'policy_id')->orderBy('id');
    }

    public function acknowledgements(): \Illuminate\Database\Eloquent\Relations\HasManyThrough
    {
        return $this->hasManyThrough(\App\Models\PolicyAcceptance::class, CompliancePolicyVersion::class, 'policy_id', 'policy_version_id');
    }
}
