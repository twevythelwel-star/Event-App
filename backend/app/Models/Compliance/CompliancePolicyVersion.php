<?php

declare(strict_types=1);

namespace App\Models\Compliance;

use App\Models\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * One version of a policy, identified by policy_name (the policy's unique identifier) and version.
 *
 * A draft changes nothing and may be edited or deleted. Once published, a version is permanent: its text and settings
 * can never change and it can never be deleted; publishing a newer version only marks it retired. So every
 * acknowledgement record (PolicyAcceptance::policy_version_id) points at the exact text that was accepted.
 *
 * A published version is in force from its effective date. When it "requires re-acknowledgement" (a material change),
 * everyone it applies to must accept it again; otherwise acceptance of an earlier version still counts.
 */
class CompliancePolicyVersion extends Model
{
    public const GROUPS = ['account' => 'Account policy (every user)', 'host' => 'Host responsibility acknowledgement (every host)'];

    public const STATUSES = ['draft' => 'Draft', 'published' => 'Published', 'retired' => 'Retired'];

    public const CATEGORIES = [
        'terms_of_service' => 'Terms of service',
        'privacy' => 'Privacy and data',
        'food_safety' => 'Food safety',
        'alcohol' => 'Alcohol',
        'host_responsibility' => 'Host responsibility',
        'event_compliance' => 'Event compliance declarations',
        'safety' => 'Safety and conduct',
        'payments_refunds' => 'Payments and refunds',
        'other' => 'Other',
    ];

    /** Who a policy applies to. "all" is every signed-in user. */
    public const ROLES = ['all' => 'Every user', 'attendee' => 'Attendees', 'event_host' => 'Event hosts', 'admin' => 'Administrators'];

    protected $fillable = ['policy_id', 'policy_name', 'policy_group', 'version', 'title', 'body', 'change_summary', 'status', 'drafted_by', 'published_by',
        'published_at', 'category', 'applicable_roles', 'applicable_jurisdictions', 'acknowledgement_mandatory', 'requires_reacknowledgement',
        'effective_at', 'source'];

    protected $casts = [
        'published_at' => 'datetime',
        'effective_at' => 'datetime',
        'applicable_roles' => 'array',
        'applicable_jurisdictions' => 'array',
        'acknowledgement_mandatory' => 'boolean',
        'requires_reacknowledgement' => 'boolean',
    ];

    protected static function booted(): void
    {
        // Every version belongs to its policy (CompliancePolicy), created with the first version.
        static::creating(function (self $version) {
            if ($version->policy_id === null && $version->policy_name) {
                $version->policy_id = CompliancePolicy::firstOrCreate(['policy_name' => $version->policy_name], ['category' => $version->category ?? 'other'])->id;
            }
        });
        // Historical versions are never overwritten: once published, only published -> retired is allowed.
        static::updating(function (self $version) {
            if ($version->getOriginal('status') === 'draft') {
                return;
            }
            $changed = array_diff(array_keys($version->getDirty()), ['status', 'updated_at']);
            $retiring = $version->getOriginal('status') === 'published' && $version->status === 'retired';
            if ($changed !== [] || ($version->isDirty('status') && !$retiring)) {
                throw new \LogicException('A published policy version cannot be changed. Draft a new version instead.');
            }
        });
        static::deleting(function (self $version) {
            if ($version->getOriginal('status') !== 'draft') {
                throw new \LogicException('A published policy version cannot be deleted.');
            }
        });
    }

    public function policy(): BelongsTo
    {
        return $this->belongsTo(CompliancePolicy::class, 'policy_id');
    }

    public function acknowledgements(): \Illuminate\Database\Eloquent\Relations\HasMany
    {
        return $this->hasMany(\App\Models\PolicyAcceptance::class, 'policy_version_id');
    }

    public function drafter(): BelongsTo
    {
        return $this->belongsTo(User::class, 'drafted_by');
    }

    public function publisher(): BelongsTo
    {
        return $this->belongsTo(User::class, 'published_by');
    }

    public function isInForce(): bool
    {
        return in_array($this->status, ['published', 'retired'], true) && ($this->effective_at === null || $this->effective_at->lte(now()));
    }

    /** @return list<string> */
    public function roles(): array
    {
        return array_values((array) ($this->applicable_roles ?: ($this->policy_group === 'host' ? ['event_host'] : ['all'])));
    }

    /** @return list<string> upper-case state codes; empty means every jurisdiction */
    public function jurisdictions(): array
    {
        return array_values(array_map('strtoupper', (array) ($this->applicable_jurisdictions ?? [])));
    }
}
