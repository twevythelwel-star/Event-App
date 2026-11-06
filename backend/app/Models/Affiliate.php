<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;

/** A promoter selling an event through their own link (?ref=CODE), with the commission the host agreed. */
class Affiliate extends Model
{
    use HasUuids;

    protected $fillable = ['event_id', 'name', 'code', 'email', 'commission_percent', 'is_active'];

    protected $casts = ['commission_percent' => 'decimal:2', 'is_active' => 'boolean'];
}
