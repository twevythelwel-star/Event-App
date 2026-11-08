<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class PolicyAcceptance extends Model
{
    use HasFactory;

    protected $table = 'policy_acceptances';

    protected $fillable = [
        'user_id',
        'policy_name',
        'policy_version',
        'acceptance_status',
        'accepted_at_utc',
        'ip_address',
        'user_agent',
        'context',
        'context_entity_id',
        'metadata',
    ];

    protected $casts = [
        'accepted_at_utc' => 'datetime',
        'metadata' => 'array',
    ];

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class, 'user_id');
    }
}

