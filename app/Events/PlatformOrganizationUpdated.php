<?php

namespace App\Events;

use App\Models\Organization;
use App\Models\User;
use Illuminate\Foundation\Events\Dispatchable;

class PlatformOrganizationUpdated
{
    use Dispatchable;

    public function __construct(
        public readonly User $actor,
        public readonly Organization $organization,
        public readonly array $before,
        public readonly array $after,
        public readonly ?string $reason = null,
    ) {}
}
