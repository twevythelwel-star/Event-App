<?php

declare(strict_types=1);

namespace App\Models\Compliance;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * One requirement topic for one jurisdiction. Until an administrator approves it from an official source its
 * status is "Verification Required" and its summary is never presented as law.
 */
class ComplianceRequirement extends Model
{
    use ReviewedRecord;

    public const RECORD_TYPE = 'requirement';

    public const TOPICS = [
        'food_permit' => 'Food permit requirements',
        'food_handler_training' => 'Food handler training requirements',
        'food_establishment' => 'Food establishment requirements',
        'temporary_food_event' => 'Temporary food event requirements',
        'alcohol_licensing' => 'Alcohol licensing requirements',
        'special_event_permit' => 'Special event permit requirements',
        'minimum_drinking_age' => 'Minimum legal drinking age',
        'restrictions' => 'Applicable restrictions and conditions',
        'required_documentation' => 'Required documentation',
    ];

    protected $fillable = ['jurisdiction_id', 'authority_id', 'topic', 'summary', 'value', 'conditions', 'required_documents',
        'legislation_citation', ...self::REVIEW_FIELDS];

    protected $casts = self::REVIEW_CASTS;

    public function authority(): BelongsTo
    {
        return $this->belongsTo(ComplianceAuthority::class, 'authority_id');
    }

    public function topicLabel(): string
    {
        return self::TOPICS[$this->topic] ?? $this->topic;
    }
}
