<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * What a host submitted for one checklist item (a permit or licence number, the issuing authority, its expiry
 * and an optional document) and an administrator's review of it. Only an administrator sets "verified" or
 * "rejected"; a verified item whose expiry date has passed counts as expired.
 */
class EventComplianceEvidence extends Model
{
    protected $table = 'event_compliance_evidence';

    public const STATUSES = ['submitted' => 'Submitted', 'pending_verification' => 'Pending Verification', 'verified' => 'Verified', 'rejected' => 'Rejected'];

    protected $fillable = ['event_id', 'item_key', 'item_title', 'status', 'reference_number', 'issuing_authority', 'expires_on', 'note',
        'document_path', 'document_name', 'document_mime', 'submitted_by', 'submitted_at', 'reviewed_by', 'reviewed_at', 'review_comment'];

    protected $casts = ['expires_on' => 'date', 'submitted_at' => 'datetime', 'reviewed_at' => 'datetime'];

    public function event(): BelongsTo
    {
        return $this->belongsTo(Event::class, 'event_id')->withoutGlobalScopes();
    }

    public function submitter(): BelongsTo
    {
        return $this->belongsTo(User::class, 'submitted_by');
    }

    public function reviewer(): BelongsTo
    {
        return $this->belongsTo(User::class, 'reviewed_by');
    }

    public function isExpired(): bool
    {
        return $this->expires_on !== null && $this->expires_on->endOfDay()->isPast();
    }

    /** The checklist status this evidence gives its item. */
    public function checklistStatus(): string
    {
        if ($this->status === 'verified' && $this->isExpired()) {
            return 'expired';
        }

        return $this->status;
    }
}
