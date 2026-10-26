<?php

namespace App\Scopes;

use App\Services\TenantContext;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Scope;

class TenantScope implements Scope
{
    /**
     * Apply the tenant scope to a given Eloquent query builder.
     * Automatically filters any query by the currently active organizer_id.
     */
    public function apply(Builder $builder, Model $model): void
    {
        if (TenantContext::isBypassed()) {
            return;
        }

        $tenantId = TenantContext::id();

        if ($tenantId) {
            $builder->where($model->getTable() . '.organizer_id', '=', $tenantId);
        }
    }
}
