<?php

namespace App\DTOs\Platform;

use Illuminate\Foundation\Http\FormRequest;

final readonly class PlatformAnalyticsFiltersDTO
{
    public function __construct(
        public ?string $dateFrom = null,
        public ?string $dateTo = null,
        public ?int $organizationId = null,
    ) {}

    public static function fromRequest(FormRequest $request): self
    {
        return new self(
            dateFrom: $request->filled('date_from') ? (string) $request->query('date_from') : null,
            dateTo: $request->filled('date_to') ? (string) $request->query('date_to') : null,
            organizationId: $request->integer('organization_id') ?: null,
        );
    }
}
