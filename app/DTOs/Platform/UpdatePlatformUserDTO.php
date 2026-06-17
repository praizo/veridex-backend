<?php

namespace App\DTOs\Platform;

use App\Http\Requests\Platform\UpdatePlatformUserRequest;

final readonly class UpdatePlatformUserDTO
{
    public function __construct(
        public bool $hasPlatformRole,
        public ?string $platformRole,
        public bool $hasSuspended,
        public ?bool $suspended,
        public bool $hasEmailVerified,
        public ?bool $emailVerified,
        public ?string $reason,
    ) {}

    public static function fromRequest(UpdatePlatformUserRequest $request): self
    {
        $validated = $request->validated();

        return new self(
            hasPlatformRole: array_key_exists('platform_role', $validated),
            platformRole: $validated['platform_role'] ?? null,
            hasSuspended: array_key_exists('suspended', $validated),
            suspended: $validated['suspended'] ?? null,
            hasEmailVerified: array_key_exists('email_verified', $validated),
            emailVerified: $validated['email_verified'] ?? null,
            reason: $validated['reason'] ?? null,
        );
    }
}
