<?php

namespace App\Listeners;

use App\Events\TeamMemberAdded;
use App\Notifications\TeamInvitationNotification;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Support\Facades\Log;
use Throwable;

class SendTeamInvitationEmail implements ShouldQueue
{
    public int $tries = 3;

    public int $timeout = 30;

    public function backoff(): array
    {
        return [10, 30, 60];
    }

    public function handle(TeamMemberAdded $event): void
    {
        Log::info('Team invitation email job started', [
            'user_id' => $event->user->uuid,
            'organization' => $event->organizationName,
            'role' => $event->role,
        ]);

        $event->user->notify(new TeamInvitationNotification(
            organizationName: $event->organizationName,
            inviterName: $event->inviterName,
            role: $event->role,
            actionUrl: $event->actionUrl,
            requiresPasswordSetup: $event->requiresPasswordSetup,
        ));

        Log::info('Team invitation email job completed', [
            'user_id' => $event->user->uuid,
        ]);
    }

    public function failed(TeamMemberAdded $event, Throwable $exception): void
    {
        Log::error('Team invitation email job failed', [
            'user_id' => $event->user->uuid,
            'organization' => $event->organizationName,
            'exception' => $exception::class,
            'message' => $exception->getMessage(),
        ]);
    }
}
