<?php

namespace App\Models;

use App\Enums\TenantRole;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class Organizer extends Model
{
    use HasFactory, HasUuids, SoftDeletes;

    protected $fillable = [
        'name',
        'slug',
        'status',
        'default_currency',
        'business_location',
        'flyer_storage_prefix',
        'payment_settings',
        'branding',
    ];

    protected $casts = [
        'payment_settings' => 'array',
        'branding' => 'array',
    ];

    /**
     * Users associated with this organizer with their respective TenantRole.
     */
    public function organizerUsers(): HasMany
    {
        return $this->hasMany(OrganizerUser::class, 'organizer_id');
    }

    /**
     * Check if a specific user has an authorized role on this organizer.
     */
    public function hasUserRole(int $userId, TenantRole $requiredRole): bool
    {
        return $this->organizerUsers()
            ->where('user_id', $userId)
            ->where('is_active', true)
            ->where('role', $requiredRole->value)
            ->exists();
    }
}
