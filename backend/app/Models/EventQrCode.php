<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * The promotional QR identity of one event. It points people to the public event page and never
 * admits anyone: admission uses each ticket's own secure token (orders.secure_ticket_token).
 *
 * @property string $public_code
 * @property array<string, mixed>|null $design
 */
class EventQrCode extends Model
{
    use HasUuids;

    public const STATUS_PENDING = 'pending';
    public const STATUS_READY = 'ready';
    public const STATUS_FAILED = 'failed';

    protected $fillable = [
        'event_id',
        'public_code',
        'preset',
        'design',
        'design_version',
        'status',
        'asset_path',
        'failure_reason',
        'attempts',
        'generated_at',
    ];

    protected $casts = [
        'design' => 'array',
        'design_version' => 'integer',
        'attempts' => 'integer',
        'generated_at' => 'datetime',
    ];

    public function event(): BelongsTo
    {
        return $this->belongsTo(Event::class)->withoutGlobalScopes();
    }

    public function isReady(): bool
    {
        return $this->status === self::STATUS_READY && $this->asset_path !== null;
    }
}
