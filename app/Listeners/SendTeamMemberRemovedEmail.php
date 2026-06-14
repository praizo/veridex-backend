<?php

namespace App\Listeners;

use App\Events\TeamMemberRemoved;
use App\Notifications\VeridexAlertNotification;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Support\Facades\Log;
use Throwable;

class SendTeamMemberRemovedEmail implements ShouldQueue
{
    public int $tries = 3;

    public int $timeout = 30;

    public function backoff(): array
    {
        return [10, 30, 60];
    }

    public function handle(TeamMemberRemoved $event): void
    {
        $event->member->notify(new VeridexAlertNotification(
            subject: "You were removed from {$event->organizationName}",
            heading: 'Organization access removed',
            message: "{$event->actorName} removed your access to {$event->organizationName} on Veridex.",
            details: [
                'Organization' => $event->organizationName,
                'Previous role' => str($event->removedRole)->headline()->toString(),
                'Removed by' => $event->actorName,
            ],
            footer: 'If you believe this was a mistake, contact an organization owner or admin.',
        ));
    }

    public function failed(TeamMemberRemoved $event, Throwable $exception): void
    {
        Log::error('Team removal email failed', [
            'user_id' => $event->member->uuid,
            'organization' => $event->organizationName,
            'exception' => $exception::class,
            'message' => $exception->getMessage(),
        ]);
    }
}
