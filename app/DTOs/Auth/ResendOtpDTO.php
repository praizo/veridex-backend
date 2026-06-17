<?php

namespace App\DTOs\Auth;

use App\Http\Requests\Auth\ResendOtpRequest;

final readonly class ResendOtpDTO
{
    public function __construct(
        public string $email,
        public string $type,
    ) {}

    public static function fromRequest(ResendOtpRequest $request): self
    {
        $validated = $request->validated();

        return new self(
            email: $validated['email'],
            type: $validated['type'],
        );
    }
}
