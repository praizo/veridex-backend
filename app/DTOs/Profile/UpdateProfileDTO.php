<?php

namespace App\DTOs\Profile;

use App\Http\Requests\Profile\UpdateProfileRequest;

final readonly class UpdateProfileDTO
{
    public function __construct(
        public string $firstName,
        public string $lastName,
        public ?string $currentPassword,
        public ?string $password,
    ) {}

    public static function fromRequest(UpdateProfileRequest $request): self
    {
        $validated = $request->validated();

        return new self(
            firstName: $validated['first_name'],
            lastName: $validated['last_name'],
            currentPassword: $validated['current_password'] ?? null,
            password: $validated['password'] ?? null,
        );
    }
}
