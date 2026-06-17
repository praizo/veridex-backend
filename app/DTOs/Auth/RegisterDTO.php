<?php

namespace App\DTOs\Auth;

use App\Http\Requests\Auth\RegisterRequest;

final readonly class RegisterDTO
{
    public function __construct(
        public string $first_name,
        public string $last_name,
        public string $email,
        public string $password,
    ) {}

    public static function fromRequest(RegisterRequest $request): self
    {
        $validated = $request->validated();

        return new self(
            first_name: $validated['first_name'],
            last_name: $validated['last_name'],
            email: $validated['email'],
            password: $validated['password'],
        );
    }
}
