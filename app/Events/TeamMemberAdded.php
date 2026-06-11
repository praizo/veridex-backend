<?php

namespace App\Events;

use App\Models\User;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class TeamMemberAdded
{
    use Dispatchable, SerializesModels;

    public function __construct(
        public readonly User $user,
        public readonly string $organizationName,
        public readonly string $inviterName,
        public readonly string $role,
        public readonly string $actionUrl,
        public readonly bool $requiresPasswordSetup,
    ) {}
}
