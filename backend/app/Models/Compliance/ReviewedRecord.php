<?php

declare(strict_types=1);

namespace App\Models\Compliance;

use App\Models\User;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * The review fields an authority or requirement record shares: its status in the administrator workflow
 * (App\Services\Compliance\ComplianceReviewWorkflow), its official source and its review dates.
 */
trait ReviewedRecord
{
    public const STATUSES = [
        'verification_required' => 'Verification Required',
        'draft' => 'Draft',
        'pending_review' => 'Pending review',
        'changes_requested' => 'Changes requested',
        'verified' => 'Verified',
        'review_due' => 'Review due',
    ];

    public const REVIEW_FIELDS = ['status', 'source_url', 'source_title', 'source_confirmed_official', 'effective_date',
        'last_verified_date', 'next_review_date', 'admin_notes', 'research_notes', 'submitted_by', 'submitted_at', 'approved_by', 'approved_at'];

    public const REVIEW_CASTS = [
        'source_confirmed_official' => 'boolean',
        'effective_date' => 'date',
        'last_verified_date' => 'date',
        'next_review_date' => 'date',
        'submitted_at' => 'datetime',
        'approved_at' => 'datetime',
    ];

    public function jurisdiction(): BelongsTo
    {
        return $this->belongsTo(ComplianceJurisdiction::class, 'jurisdiction_id');
    }

    public function submitter(): BelongsTo
    {
        return $this->belongsTo(User::class, 'submitted_by');
    }

    public function approver(): BelongsTo
    {
        return $this->belongsTo(User::class, 'approved_by');
    }

    public function reviews(): HasMany
    {
        return $this->hasMany(ComplianceRecordReview::class, 'record_id')
            ->where('record_type', static::RECORD_TYPE)->latest('id');
    }

    public function statusLabel(): string
    {
        return self::STATUSES[$this->status] ?? $this->status;
    }

    /** Only an approved record within its review date may be presented as verified. */
    public function isVerified(): bool
    {
        return $this->status === 'verified' && ($this->next_review_date === null || $this->next_review_date->isFuture());
    }
}
