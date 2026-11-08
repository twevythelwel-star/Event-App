<?php

declare(strict_types=1);

namespace App\Models\Compliance;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/** A place in the hierarchy: country → state → county → city or municipality. */
class ComplianceJurisdiction extends Model
{
    public const TYPES = ['country' => 'Country', 'state' => 'State', 'county' => 'County', 'municipality' => 'City or municipality'];

    protected $fillable = ['parent_id', 'type', 'name', 'code', 'state_code', 'census_class', 'data_source'];

    public function parent(): BelongsTo
    {
        return $this->belongsTo(self::class, 'parent_id');
    }

    public function children(): HasMany
    {
        return $this->hasMany(self::class, 'parent_id');
    }

    public function authorities(): HasMany
    {
        return $this->hasMany(ComplianceAuthority::class, 'jurisdiction_id');
    }

    public function requirements(): HasMany
    {
        return $this->hasMany(ComplianceRequirement::class, 'jurisdiction_id');
    }

    public function typeLabel(): string
    {
        return self::TYPES[$this->type] ?? ucfirst($this->type);
    }

    /** @return list<self> from the country down to this jurisdiction */
    public function lineage(): array
    {
        $chain = [];
        for ($node = $this; $node !== null; $node = $node->parent) {
            array_unshift($chain, $node);
        }

        return $chain;
    }
}
