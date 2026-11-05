<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;

class CulturalRegion extends Model
{
    public $incrementing = false;

    protected $keyType = 'string';

    protected $fillable = ['id', 'cultural_category_id', 'name', 'sort_order', 'is_active'];

    protected $casts = ['is_active' => 'boolean'];

    public function category(): BelongsTo
    {
        return $this->belongsTo(CulturalCategory::class, 'cultural_category_id');
    }

    public function countries(): BelongsToMany
    {
        return $this->belongsToMany(Country::class, 'cultural_region_countries', 'cultural_region_id', 'country_id')
            ->where('countries.is_active', true)->orderBy('countries.sort_order');
    }
}
