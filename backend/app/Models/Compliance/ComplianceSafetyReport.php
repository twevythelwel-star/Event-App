<?php

declare(strict_types=1);

namespace App\Models\Compliance;

use App\Models\Event;
use App\Models\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/** A safety issue someone reported about an event, and a compliance administrator's handling of it. */
class ComplianceSafetyReport extends Model
{
    public const CATEGORIES = [
        'food_safety' => 'Food safety or food poisoning',
        'allergen' => 'Allergen or ingredient labelling',
        'alcohol' => 'Alcohol service or underage drinking',
        'venue' => 'Venue, crowd or fire safety',
        'harassment' => 'Harassment or threatening behaviour',
        'other' => 'Something else',
    ];

    public const STATUSES = ['open' => 'Open', 'investigating' => 'Investigating', 'resolved' => 'Resolved', 'dismissed' => 'Dismissed'];

    public const PRIORITIES = ['normal' => 'Normal', 'urgent' => 'Urgent'];

    protected $fillable = ['event_id', 'reported_by', 'category', 'description', 'occurred_on', 'may_contact', 'status', 'priority',
        'assigned_to', 'resolution', 'resolved_by', 'resolved_at'];

    protected $casts = ['occurred_on' => 'date', 'may_contact' => 'boolean', 'resolved_at' => 'datetime'];

    public function event(): BelongsTo
    {
        return $this->belongsTo(Event::class, 'event_id')->withoutGlobalScopes();
    }

    public function reporter(): BelongsTo
    {
        return $this->belongsTo(User::class, 'reported_by');
    }

    public function assignee(): BelongsTo
    {
        return $this->belongsTo(User::class, 'assigned_to');
    }

    public function resolver(): BelongsTo
    {
        return $this->belongsTo(User::class, 'resolved_by');
    }

    public function isOpen(): bool
    {
        return in_array($this->status, ['open', 'investigating'], true);
    }
}
