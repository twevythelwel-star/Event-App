<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

/** A sponsorship package the host offers, e.g. "Gold: logo on tickets and the stage banner". */
class SponsorshipPackage extends Model
{
    use HasUuids;

    protected $fillable = ['event_id', 'level', 'name', 'benefits', 'price_minor', 'quantity'];

    protected $casts = ['price_minor' => 'integer', 'quantity' => 'integer'];

    public function sponsors(): HasMany
    {
        return $this->hasMany(EventSponsor::class, 'package_id');
    }
}
