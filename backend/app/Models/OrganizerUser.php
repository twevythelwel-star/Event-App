<?php

namespace App\Models;

use App\Enums\TenantRole;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class OrganizerUser extends Model
{
    use HasFactory;

    protected $table = 'organizer_users';

    protected $fillable = [
        'organizer_id',
        'user_id',
        'role',
        'station_assignment',
        'is_active',
        'custom_permissions',
        'last_active_at',
    ];

    protected $casts = [
        'role' => TenantRole::class,
        'is_active' => 'boolean',
        'custom_permissions' => 'array',
        'last_active_at' => 'datetime',
    ];

    public function organizer(): BelongsTo
    {
        return $this->belongsTo(Organizer::class, 'organizer_id');
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class, 'user_id');
    }
}
