<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class EventComplianceAcknowledgement extends Model
{
    use HasUuids;

    protected $table = 'event_compliance_acknowledgements';

    protected $fillable = [
        'event_id',
        'user_id',
        'policy_version',
        'acknowledgement_context',
        'jurisdiction_country',
        'jurisdiction_state',
        'jurisdiction_county',
        'jurisdiction_municipality',
        'supports_food_ordering',
        'food_permit_status',
        'food_permit_number',
        'serves_alcohol',
        'alcohol_age_verification_required',
        'alcohol_permit_status',
        'alcohol_permit_number',
        'food_safety_declarations',
        'alcohol_declarations',
        'general_legal_declarations',
        'all_declarations_accepted',
        'arrangement_fingerprint',
        'accepted_at_utc',
        'ip_address',
        'user_agent',
        'superseded_at_utc',
        'superseded_reason',
    ];

    protected $casts = [
        'supports_food_ordering' => 'boolean',
        'serves_alcohol' => 'boolean',
        'alcohol_age_verification_required' => 'boolean',
        'food_safety_declarations' => 'array',
        'alcohol_declarations' => 'array',
        'general_legal_declarations' => 'array',
        'all_declarations_accepted' => 'boolean',
        'accepted_at_utc' => 'datetime',
        'superseded_at_utc' => 'datetime',
    ];

    public function event(): BelongsTo
    {
        return $this->belongsTo(Event::class, 'event_id', 'id');
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class, 'user_id', 'id');
    }

    public function isSuperseded(): bool
    {
        return $this->superseded_at_utc !== null;
    }

    public function jurisdictionLabel(): string
    {
        $parts = array_filter([
            $this->jurisdiction_municipality,
            $this->jurisdiction_county,
            $this->jurisdiction_state,
            $this->jurisdiction_country,
        ], fn ($v) => is_string($v) && trim($v) !== '');

        return implode(', ', $parts) ?: 'Unspecified jurisdiction';
    }
}

