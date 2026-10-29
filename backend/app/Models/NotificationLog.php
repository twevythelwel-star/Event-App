<?php

namespace App\Models;

use App\Traits\BelongsToTenant;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class NotificationLog extends Model
{
    use HasFactory, HasUuids, BelongsToTenant;

    protected $table = 'notification_logs';

    protected $fillable = [
        'organizer_id',
        'idempotency_key',
        'order_id',
        'order_number',
        'ticket_code',
        'customer_name',
        'recipient',
        'channel',
        'trigger',
        'title',
        'message',
        'email_subject',
        'email_html',
        'status',
        'provider',
        'provider_message_id',
        'retry_count',
        'retry_suppressed',
        'failure_reason',
        'attempts_history',
        'queued_at',
        'sent_at',
        'delivered_at',
        'read_at',
    ];

    protected $casts = [
        'attempts_history' => 'array',
        'retry_suppressed' => 'boolean',
        'queued_at' => 'datetime',
        'sent_at' => 'datetime',
        'delivered_at' => 'datetime',
        'read_at' => 'datetime',
    ];

    /**
     * Scope to find existing log by idempotency key
     */
    public static function findByIdempotency(string $key): ?self
    {
        return static::where('idempotency_key', $key)->first();
    }

    /**
     * Record an attempt in the history timeline
     */
    public function logAttempt(string $status, string $note): void
    {
        $history = $this->attempts_history ?? [];
        $history[] = [
            'timestamp' => now()->toIso8601String(),
            'status' => $status,
            'note' => $note,
        ];
        $this->attempts_history = $history;
        $this->save();
    }
}
