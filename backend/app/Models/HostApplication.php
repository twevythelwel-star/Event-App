<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class HostApplication extends Model
{
    use HasFactory;

    protected $fillable = [
        'user_id',
        'organization_name',
        'description',
        'country',
        'contact_phone',
        'website_or_social',
        'status',
        'reviewed_by',
        'reviewed_at',
        'rejection_reason',
        'review_notes',
        'legal_full_name',
        'business_entity_type',
        'government_id_type',
        'government_id_last4',
        'identity_verification_status',
        'food_handler_permit_number',
        'alcohol_license_number',
        'compliance_acknowledged_at',
    ];

    protected $casts = [
        'reviewed_at' => 'datetime',
        'compliance_acknowledged_at' => 'datetime',
    ];

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class, 'user_id');
    }

    public function reviewer(): BelongsTo
    {
        return $this->belongsTo(User::class, 'reviewed_by');
    }

    public function identityVerification(): \Illuminate\Database\Eloquent\Relations\HasOne
    {
        return $this->hasOne(HostIdentityVerification::class, 'host_application_id')->latestOfMany();
    }

    public function isPending(): bool
    {
        return $this->status === 'pending';
    }

    public function isApproved(): bool
    {
        return $this->status === 'approved';
    }

    public function isRejected(): bool
    {
        return $this->status === 'rejected';
    }
}
