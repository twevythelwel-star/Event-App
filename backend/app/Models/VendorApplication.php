<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/** A business applying to take part in an event as a vendor, exhibitor or sponsor, and the host's decision. */
class VendorApplication extends Model
{
    use HasUuids;

    public const CATEGORIES = [
        'food' => 'Food vendor',
        'merchandise' => 'Merchandise vendor',
        'craft' => 'Craft vendor',
        'exhibitor' => 'Exhibitor',
        'sponsor' => 'Sponsor',
    ];

    public const STATUSES = ['pending' => 'Waiting for a decision', 'approved' => 'Approved', 'declined' => 'Declined', 'withdrawn' => 'Withdrawn'];

    public const FEE_STATUSES = ['not_due' => 'No fee', 'unpaid' => 'Fee due', 'paid' => 'Fee paid', 'waived' => 'Fee waived'];

    protected $fillable = ['event_id', 'user_id', 'offer_id', 'category', 'business_name', 'contact_name', 'email', 'phone', 'description',
        'website', 'needs', 'show_publicly', 'status', 'decision_note', 'decided_by', 'decided_at', 'booth_label', 'venue_area_id', 'fee_minor', 'fee_status'];

    protected $casts = ['show_publicly' => 'boolean', 'decided_at' => 'datetime', 'fee_minor' => 'integer'];

    public function event(): BelongsTo
    {
        return $this->belongsTo(Event::class)->withoutGlobalScopes();
    }

    public function offer(): BelongsTo
    {
        return $this->belongsTo(VendorOffer::class, 'offer_id');
    }

    public function applicant(): BelongsTo
    {
        return $this->belongsTo(User::class, 'user_id');
    }

    public function categoryLabel(): string
    {
        return self::CATEGORIES[$this->category] ?? ucfirst((string) $this->category);
    }

    public function statusLabel(): string
    {
        return self::STATUSES[$this->status] ?? ucfirst((string) $this->status);
    }
}
