<?php

namespace App\Events;

use App\Models\User;
use Illuminate\Foundation\Events\Dispatchable;

class PlatformUserUpdated
{
    use Dispatchable;

    public function __construct(
        public readonly User $actor,
        public readonly User $user,
        public readonly array $before,
        public readonly array $after,
        public readonly ?string $reason = null,
    ) {}
}
