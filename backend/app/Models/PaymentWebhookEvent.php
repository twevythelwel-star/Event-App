<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;

/**
 * A processor event as received (after its signature was verified). Unique per provider and event id,
 * which is what makes webhook processing idempotent. The payload itself is not kept, only its hash.
 */
class PaymentWebhookEvent extends Model
{
    use HasUuids;

    protected $fillable = ['provider', 'event_id', 'type', 'payload_sha256', 'result', 'note', 'processed_at'];

    protected $casts = ['processed_at' => 'datetime'];
}
