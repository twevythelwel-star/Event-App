<?php

declare(strict_types=1);

namespace App\Models\Compliance;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

/** An agency that regulates food or alcohol in a jurisdiction, with its official website and rules. */
class ComplianceAuthority extends Model
{
    use ReviewedRecord;

    public const RECORD_TYPE = 'authority';

    public const AREAS = [
        'food' => 'Food safety and food service',
        'agriculture' => 'Agriculture',
        'alcohol' => 'Alcohol licensing and control',
        'revenue' => 'Revenue and taxation',
        'general' => 'General',
    ];

    protected $fillable = ['jurisdiction_id', 'area', 'role', 'name', 'website_url', 'legislation_url', 'legislation_title', ...self::REVIEW_FIELDS];

    protected $casts = self::REVIEW_CASTS;

    public function requirements(): HasMany
    {
        return $this->hasMany(ComplianceRequirement::class, 'authority_id');
    }
}
