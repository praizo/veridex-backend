<?php

namespace App\Events;

use App\Models\User;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class TeamMemberRemoved
{
    use Dispatchable, SerializesModels;

    public function __construct(
        public readonly User $member,
        public readonly string $organizationName,
        public readonly string $actorName,
        public readonly string $removedRole,
    ) {}
}
