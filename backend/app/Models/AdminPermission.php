<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class AdminPermission extends Model
{
    use HasFactory;

    protected $table = 'admin_permissions';

    protected $fillable = [
        'user_id',
        'scope',
        'can_manage_events',
        'can_moderate_content',
        'can_support_orders',
        'can_view_payments',
        'can_view_reports',
        'can_access_audit_logs',
        'assigned_organizer_ids',
    ];

    protected $casts = [
        'can_manage_events' => 'boolean',
        'can_moderate_content' => 'boolean',
        'can_support_orders' => 'boolean',
        'can_view_payments' => 'boolean',
        'can_view_reports' => 'boolean',
        'can_access_audit_logs' => 'boolean',
        'assigned_organizer_ids' => 'array',
    ];

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class, 'user_id');
    }

    /**
     * Check if administrator has permission for an organizer.
     */
    public function canAccessOrganizer(string $organizerId): bool
    {
        if ($this->scope === 'all') {
            return true;
        }

        $assigned = $this->assigned_organizer_ids ?? [];
        return in_array($organizerId, $assigned);
    }
}
