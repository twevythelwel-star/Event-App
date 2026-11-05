<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class CulturalCategory extends Model
{
    public $incrementing = false;

    protected $keyType = 'string';

    protected $fillable = ['id', 'name', 'sort_order', 'is_active'];

    protected $casts = ['is_active' => 'boolean'];

    public function regions(): HasMany
    {
        return $this->hasMany(CulturalRegion::class)->where('is_active', true)->orderBy('sort_order');
    }
}
