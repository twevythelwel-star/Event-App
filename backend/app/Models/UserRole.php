<?php

namespace App\Models;

use App\Enums\AccountType;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class UserRole extends Model
{
    use HasFactory;

    protected $table = 'user_roles';

    protected $fillable = [
        'user_id',
        'role',
        'is_active',
        'assigned_by',
        'assigned_at',
    ];

    protected $casts = [
        'is_active' => 'boolean',
        'assigned_at' => 'datetime',
    ];

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class, 'user_id');
    }

    public function assignedByUser(): BelongsTo
    {
        return $this->belongsTo(User::class, 'assigned_by');
    }

    public function getRoleEnum(): ?AccountType
    {
        return AccountType::tryFrom($this->role);
    }
}
