<?php

namespace App\DTOs\Auth;

use App\Http\Requests\Auth\ResetPasswordRequest;

final readonly class ResetPasswordDTO
{
    public function __construct(
        public string $email,
        public string $token,
        public string $password,
        public string $passwordConfirmation,
    ) {}

    public static function fromRequest(ResetPasswordRequest $request): self
    {
        $validated = $request->validated();

        return new self(
            email: $validated['email'],
            token: $validated['token'],
            password: $validated['password'],
            passwordConfirmation: $validated['password_confirmation'] ?? (string) $request->input('password_confirmation'),
        );
    }
}
