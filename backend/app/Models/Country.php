<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Country extends Model
{
    public $incrementing = false;

    protected $keyType = 'string';

    protected $fillable = ['id', 'cultural_region_id', 'name', 'flag_emoji', 'cuisine_image_url', 'sort_order', 'is_active'];

    protected $casts = ['is_active' => 'boolean'];

    public function regions(): BelongsToMany
    {
        return $this->belongsToMany(CulturalRegion::class, 'cultural_region_countries', 'country_id', 'cultural_region_id')
            ->where('cultural_regions.is_active', true)->orderBy('cultural_regions.sort_order');
    }

    public function cuisines(): HasMany
    {
        return $this->hasMany(Cuisine::class)->where('is_active', true)->orderBy('sort_order');
    }
}
