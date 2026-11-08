<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * One acknowledgement of one policy version by one person, in one context: account_registration, host_application,
 * event_creation, event_material_change_reacknowledgement, policy_update (and a few others). Records are append-only:
 * a new acknowledgement is a new row, and earlier ones are never changed or removed.
 */
class PolicyAcceptance extends Model
{
    use HasFactory;

    protected $table = 'policy_acceptances';

    protected static function booted(): void
    {
        // Link each new record to the exact policy version accepted, when it is known.
        static::creating(function (self $acceptance) {
            if ($acceptance->policy_version_id === null && $acceptance->policy_name && $acceptance->policy_version) {
                try {
                    $acceptance->policy_version_id = \App\Models\Compliance\CompliancePolicyVersion::where('policy_name', $acceptance->policy_name)
                        ->where('version', $acceptance->policy_version)->value('id');
                } catch (\Throwable) {
                    // Table not migrated (isolated runner): leave it unlinked.
                }
            }
        });
        static::updating(fn () => throw new \LogicException('Acknowledgement records cannot be changed; record a new acknowledgement instead.'));
        static::deleting(fn () => throw new \LogicException('Acknowledgement records cannot be deleted.'));
    }

    public function policyVersion(): BelongsTo
    {
        return $this->belongsTo(\App\Models\Compliance\CompliancePolicyVersion::class, 'policy_version_id');
    }

    protected $fillable = [
        'user_id',
        'policy_name',
        'policy_version',
        'acceptance_status',
        'accepted_at_utc',
        'ip_address',
        'user_agent',
        'context',
        'context_entity_id',
        'metadata',
        'policy_version_id',
    ];

    protected $casts = [
        'accepted_at_utc' => 'datetime',
        'metadata' => 'array',
    ];

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class, 'user_id');
    }
}

