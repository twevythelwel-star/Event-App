<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Dish extends Model
{
    use HasUuids;

    protected $fillable = ['cuisine_id', 'name', 'alternative_spellings', 'is_fusion', 'dietary_type', 'sort_order', 'is_active'];

    protected $casts = [
        'alternative_spellings' => 'array',
        'is_fusion' => 'boolean',
        'is_active' => 'boolean',
    ];

    public function cuisine(): BelongsTo
    {
        return $this->belongsTo(Cuisine::class);
    }
}
