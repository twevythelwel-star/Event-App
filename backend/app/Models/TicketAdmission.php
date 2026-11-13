<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;

/** The record that an attendee ticket was admitted; unique per ticket (see the migration). */
class TicketAdmission extends Model
{
    use HasUuids;

    public const VIA_ATTENDEE_TICKET = 'attendee_ticket';
    public const VIA_ORDER_PASS = 'order_pass';

    protected $fillable = ['attendee_ticket_id', 'order_id', 'event_id', 'admitted_by', 'admitted_via', 'request_id', 'admitted_at'];

    protected $casts = ['admitted_at' => 'datetime'];
}
