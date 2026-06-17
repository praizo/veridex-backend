<?php

namespace App\Services\Platform\Concerns;

use App\DTOs\Platform\PlatformListFiltersDTO;
use Illuminate\Database\Eloquent\Builder;

trait PaginatesPlatformResults
{
    protected function applySort(Builder $query, PlatformListFiltersDTO $filters, array $allowed, string $default): void
    {
        $sort = $allowed[$filters->sort] ?? $default;
        $query->orderBy($sort, $filters->direction);
    }

    protected function paginated($paginator): array
    {
        return [
            'data' => $paginator->items(),
            'meta' => [
                'current_page' => $paginator->currentPage(),
                'last_page' => $paginator->lastPage(),
                'per_page' => $paginator->perPage(),
                'total' => $paginator->total(),
            ],
        ];
    }
}
