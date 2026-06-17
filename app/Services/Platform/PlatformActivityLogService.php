<?php

namespace App\Services\Platform;

use App\DTOs\Platform\PlatformListFiltersDTO;
use App\Models\ActivityLog;
use App\Services\Platform\Concerns\PaginatesPlatformResults;
use Illuminate\Database\Eloquent\Builder;

class PlatformActivityLogService
{
    use PaginatesPlatformResults;

    public function list(PlatformListFiltersDTO $filters): array
    {
        $query = ActivityLog::query()->with(['user:id,first_name,last_name,email', 'organization:id,name']);

        $query
            ->when($filters->organizationId, fn (Builder $query, int $organizationId) => $query->where('organization_id', $organizationId))
            ->when($filters->dateFrom, fn (Builder $query, string $date) => $query->whereDate('created_at', '>=', $date))
            ->when($filters->dateTo, fn (Builder $query, string $date) => $query->whereDate('created_at', '<=', $date))
            ->when($filters->search, function (Builder $query, string $search) {
                $query->where(function (Builder $nested) use ($search) {
                    $nested->where('action', 'like', "%{$search}%")
                        ->orWhere('description', 'like', "%{$search}%")
                        ->orWhereHas('user', function (Builder $userQuery) use ($search) {
                            $userQuery->where('first_name', 'like', "%{$search}%")
                                ->orWhere('last_name', 'like', "%{$search}%")
                                ->orWhere('email', 'like', "%{$search}%");
                        })
                        ->orWhereHas('organization', fn (Builder $orgQuery) => $orgQuery->where('name', 'like', "%{$search}%"));
                });
            });

        $this->applySort($query, $filters, ['created_at' => 'created_at', 'action' => 'action'], 'created_at');

        return $this->paginated($query->paginate($filters->perPage));
    }
}
