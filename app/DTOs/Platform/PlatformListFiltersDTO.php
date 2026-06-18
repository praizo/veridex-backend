<?php

namespace App\DTOs\Platform;

use Illuminate\Foundation\Http\FormRequest;

final readonly class PlatformListFiltersDTO
{
    public function __construct(
        public int $page = 1,
        public int $perPage = 15,
        public ?string $search = null,
        public ?string $status = null,
        public ?string $onboardingStatus = null,
        public ?string $organizationId = null,
        public ?string $userId = null,
        public ?string $dateFrom = null,
        public ?string $dateTo = null,
        public ?string $sort = null,
        public string $direction = 'desc',
    ) {}

    public static function fromRequest(FormRequest $request): self
    {
        return new self(
            page: $request->integer('page', 1),
            perPage: $request->integer('per_page', 15),
            search: $request->filled('search') ? trim((string) $request->query('search')) : null,
            status: $request->filled('status') ? (string) $request->query('status') : null,
            onboardingStatus: $request->filled('onboarding_status') ? (string) $request->query('onboarding_status') : null,
            organizationId: $request->filled('organization_id') ? trim((string) $request->query('organization_id')) : null,
            userId: $request->filled('user_id') ? trim((string) $request->query('user_id')) : null,
            dateFrom: $request->filled('date_from') ? (string) $request->query('date_from') : null,
            dateTo: $request->filled('date_to') ? (string) $request->query('date_to') : null,
            sort: $request->filled('sort') ? (string) $request->query('sort') : null,
            direction: $request->query('direction') === 'asc' ? 'asc' : 'desc',
        );
    }
}
