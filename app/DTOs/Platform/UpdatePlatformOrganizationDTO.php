<?php

namespace App\DTOs\Platform;

use App\Http\Requests\Platform\UpdatePlatformOrganizationRequest;

final readonly class UpdatePlatformOrganizationDTO
{
    public function __construct(
        public array $businessFields,
        public bool $hasPlatformStatus,
        public ?string $platformStatus,
        public bool $hasOnboardingStatus,
        public ?string $onboardingStatus,
        public bool $hasVerified,
        public ?bool $verified,
        public bool $hasAdminNotes,
        public ?string $adminNotes,
        public ?string $reason,
    ) {}

    public static function fromRequest(UpdatePlatformOrganizationRequest $request): self
    {
        $validated = $request->validated();
        $businessFields = collect($validated)
            ->only([
                'name',
                'tin',
                'email',
                'telephone',
                'street_name',
                'city_name',
                'postal_zone',
                'country_code',
                'business_description',
                'service_id',
                'nrs_business_id',
            ])
            ->all();

        return new self(
            businessFields: $businessFields,
            hasPlatformStatus: array_key_exists('platform_status', $validated),
            platformStatus: $validated['platform_status'] ?? null,
            hasOnboardingStatus: array_key_exists('onboarding_status', $validated),
            onboardingStatus: $validated['onboarding_status'] ?? null,
            hasVerified: array_key_exists('verified', $validated),
            verified: $validated['verified'] ?? null,
            hasAdminNotes: array_key_exists('admin_notes', $validated),
            adminNotes: $validated['admin_notes'] ?? null,
            reason: $validated['reason'] ?? null,
        );
    }
}
