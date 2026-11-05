<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;

class EventDish extends Model
{
    use HasUuids;

    protected $fillable = [
        'event_id', 'dish_id', 'country_id', 'custom_name', 'alternative_spellings', 'is_fusion', 'dietary_type',
    ];

    protected $casts = ['alternative_spellings' => 'array', 'is_fusion' => 'boolean'];
}
