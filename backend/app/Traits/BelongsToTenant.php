<?php

namespace App\Traits;

use App\Models\Organizer;
use App\Scopes\TenantScope;
use App\Services\TenantContext;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

trait BelongsToTenant
{
    /**
     * Boot the trait and register the global TenantScope.
     */
    protected static function bootBelongsToTenant(): void
    {
        static::addGlobalScope(new TenantScope());

        // Automatically assign active tenant ID when creating records
        static::creating(function ($model) {
            if (!$model->organizer_id && TenantContext::id()) {
                $model->organizer_id = TenantContext::id();
            }
        });
    }

    /**
     * Relationship to the Organizer tenant model.
     */
    public function organizer(): BelongsTo
    {
        return $this->belongsTo(Organizer::class, 'organizer_id');
    }

    /**
     * Scope a query to ignore tenant boundaries (for authorized platform admin use only).
     */
    public function scopeAllTenants(Builder $query): Builder
    {
        return $query->withoutGlobalScope(TenantScope::class);
    }

    /**
     * Scope a query to an explicit tenant UUID.
     */
    public function scopeForOrganizer(Builder $query, string $organizerId): Builder
    {
        return $query->withoutGlobalScope(TenantScope::class)->where($this->getTable() . '.organizer_id', $organizerId);
    }
}
