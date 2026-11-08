<?php

declare(strict_types=1);

namespace App\Models\Compliance;

use App\Models\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * One version of a platform policy or host acknowledgement. A draft changes nothing. Publishing a version makes it
 * the current one (PolicyCatalog): everyone it applies to must accept it again, and the version it replaces is
 * retired. Published and retired versions are never edited, so every acceptance points at the exact text accepted.
 */
class CompliancePolicyVersion extends Model
{
    public const GROUPS = ['account' => 'Account policy (every user)', 'host' => 'Host responsibility acknowledgement (every host)'];

    public const STATUSES = ['draft' => 'Draft', 'published' => 'Published', 'retired' => 'Retired'];

    protected $fillable = ['policy_name', 'policy_group', 'version', 'title', 'body', 'change_summary', 'status', 'drafted_by', 'published_by', 'published_at'];

    protected $casts = ['published_at' => 'datetime'];

    public function drafter(): BelongsTo
    {
        return $this->belongsTo(User::class, 'drafted_by');
    }

    public function publisher(): BelongsTo
    {
        return $this->belongsTo(User::class, 'published_by');
    }
}
