<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class SecurityAuditLog extends Model
{
    use HasFactory, HasUuids;

    protected $table = 'security_audit_logs';

    public $timestamps = false;

    protected $fillable = [
        'id',
        'user_id',
        'actor_role',
        'action',
        'target_entity',
        'target_id',
        'ip_address',
        'user_agent',
        'details',
        'is_pii_access',
        'outcome',
        'organizer_id',
        'event_id',
        'created_at',
    ];

    protected $casts = [
        'is_pii_access' => 'boolean',
        'created_at' => 'datetime',
    ];

    /**
     * Audit history is append-only: no application code path may rewrite or remove a record.
     */
    protected static function booted(): void
    {
        static::updating(function () {
            throw new \LogicException('Security audit records are immutable.');
        });

        static::deleting(function () {
            throw new \LogicException('Security audit records cannot be deleted.');
        });
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class, 'user_id');
    }
}
