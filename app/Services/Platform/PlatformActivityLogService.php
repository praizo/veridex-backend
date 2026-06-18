<?php

namespace App\Services\Platform;

use App\DTOs\Platform\PlatformListFiltersDTO;
use App\Http\Resources\ActivityLogResource;
use App\Models\ActivityLog;
use App\Models\Organization;
use App\Models\User;
use App\Services\Platform\Concerns\PaginatesPlatformResults;
use Illuminate\Database\Eloquent\Builder;

class PlatformActivityLogService
{
    use PaginatesPlatformResults;

    public function list(PlatformListFiltersDTO $filters): array
    {
        $query = ActivityLog::query()->with(['user:id,uuid,first_name,last_name,email', 'organization:id,uuid,name']);

        $query
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

        if ($filters->organizationId !== null) {
            $organizationId = $this->resolveOrganizationId($filters->organizationId);
            $organizationId ? $query->where('organization_id', $organizationId) : $query->whereRaw('1 = 0');
        }

        if ($filters->userId !== null) {
            $userId = $this->resolveUserId($filters->userId);
            $userId ? $query->where('user_id', $userId) : $query->whereRaw('1 = 0');
        }

        $this->applySort($query, $filters, ['created_at' => 'created_at', 'action' => 'action'], 'created_at');

        return $this->paginated(
            $query->paginate($filters->perPage),
            fn (ActivityLog $log) => (new ActivityLogResource($log))->resolve()
        );
    }

    private function resolveOrganizationId(string $value): ?int
    {
        return Organization::query()
            ->where('uuid', $value)
            ->orWhere('id', $value)
            ->value('id');
    }

    private function resolveUserId(string $value): ?int
    {
        return User::query()
            ->where('uuid', $value)
            ->orWhere('id', $value)
            ->value('id');
    }
}
