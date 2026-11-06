<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;

/** One order's use of a discount code, with the amount it took off. */
class DiscountRedemption extends Model
{
    use HasUuids;

    protected $fillable = ['discount_code_id', 'order_id', 'user_id', 'amount_minor'];

    protected $casts = ['amount_minor' => 'integer'];
}
