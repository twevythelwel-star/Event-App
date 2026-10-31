<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class UsState extends Model
{
    protected $primaryKey = 'code';

    public $incrementing = false;

    protected $keyType = 'string';

    protected $fillable = ['code', 'name', 'default_timezone', 'is_state_or_dc', 'is_enabled'];

    protected $casts = ['is_state_or_dc' => 'boolean', 'is_enabled' => 'boolean'];

    public function markets(): HasMany
    {
        return $this->hasMany(TargetMarket::class, 'state_code', 'code');
    }

    public function events(): HasMany
    {
        return $this->hasMany(Event::class, 'state_code', 'code');
    }
}
