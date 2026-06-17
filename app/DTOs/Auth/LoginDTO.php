<?php

namespace App\DTOs\Auth;

use App\Http\Requests\Auth\LoginRequest;

final readonly class LoginDTO
{
    public function __construct(
        public string $email,
        public string $password,
    ) {}

    public static function fromRequest(LoginRequest $request): self
    {
        $validated = $request->validated();

        return new self(
            email: $validated['email'],
            password: $validated['password'],
        );
    }
}
