<?php

namespace App\DTOs;

use Illuminate\Http\Request;

final readonly class RequestContextDTO
{
    public function __construct(
        public ?string $ip,
        public ?string $userAgent,
    ) {}

    public static function fromRequest(Request $request): self
    {
        return new self(
            ip: $request->ip(),
            userAgent: $request->userAgent(),
        );
    }
}
