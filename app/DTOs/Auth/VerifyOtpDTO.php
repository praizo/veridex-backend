<?php

namespace App\DTOs\Auth;

use App\Http\Requests\Auth\VerifyOtpRequest;

final readonly class VerifyOtpDTO
{
    public function __construct(
        public string $email,
        public string $code,
        public string $type,
    ) {}

    public static function fromRequest(VerifyOtpRequest $request): self
    {
        $validated = $request->validated();

        return new self(
            email: $validated['email'],
            code: $validated['code'],
            type: $validated['type'],
        );
    }
}
