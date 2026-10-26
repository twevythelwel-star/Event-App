<?php

namespace App\Models;

use App\Traits\BelongsToTenant;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class HandoverAuditLog extends Model
{
    use HasFactory, HasUuids, BelongsToTenant;

    protected $fillable = [
        'organizer_id',
        'order_id',
        'order_number',
        'ticket_code',
        'customer_name',
        'item_name',
        'item_category',
        'quantity_collected',
        'remaining_quantity',
        'timestamp',
        'staff_name',
        'station',
        'action_type',
        'override_reason',
        'supervisor_name',
    ];

    protected $casts = [
        'quantity_collected' => 'integer',
        'remaining_quantity' => 'integer',
        'timestamp' => 'datetime',
    ];

    public function order(): BelongsTo
    {
        return $this->belongsTo(Order::class, 'order_id');
    }
}
