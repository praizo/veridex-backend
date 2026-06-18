<?php

namespace App\Models\Concerns;

use App\Support\Tenancy\TenantContext;
use Illuminate\Database\Eloquent\Builder;

trait BelongsToOrganization
{
    protected static function bootBelongsToOrganization(): void
    {
        static::addGlobalScope('organization', function (Builder $query): void {
            $organizationId = app(TenantContext::class)->id();

            if ($organizationId !== null) {
                $query->where(
                    $query->getModel()->getTable().'.organization_id',
                    $organizationId
                );
            }
        });

        static::creating(function ($model): void {
            $organizationId = app(TenantContext::class)->id();

            if ($organizationId !== null && empty($model->organization_id)) {
                $model->organization_id = $organizationId;
            }
        });
    }
}
