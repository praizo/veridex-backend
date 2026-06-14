<?php

namespace App\Events;

use App\Models\User;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class AccountSecurityAlertRequested
{
    use Dispatchable, SerializesModels;

    public function __construct(
        public readonly User $user,
        public readonly string $subject,
        public readonly string $heading,
        public readonly string $message,
        public readonly array $details = [],
        public readonly ?string $actionText = null,
        public readonly ?string $actionUrl = null,
        public readonly ?string $footer = null,
    ) {}
}
