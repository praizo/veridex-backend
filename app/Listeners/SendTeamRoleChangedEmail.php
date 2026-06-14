<?php

namespace App\Listeners;

use App\Events\TeamMemberRoleChanged;
use App\Notifications\VeridexAlertNotification;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Support\Facades\Log;
use Throwable;

class SendTeamRoleChangedEmail implements ShouldQueue
{
    public int $tries = 3;

    public int $timeout = 30;

    public function backoff(): array
    {
        return [10, 30, 60];
    }

    public function handle(TeamMemberRoleChanged $event): void
    {
        $event->member->notify(new VeridexAlertNotification(
            subject: "Your role changed in {$event->organizationName}",
            heading: 'Your team role was updated',
            message: "{$event->actorName} changed your role in {$event->organizationName}.",
            details: [
                'Organization' => $event->organizationName,
                'Previous role' => str($event->oldRole)->headline()->toString(),
                'New role' => str($event->newRole)->headline()->toString(),
                'Changed by' => $event->actorName,
            ],
            footer: 'If this change looks wrong, contact an organization owner or admin.',
        ));
    }

    public function failed(TeamMemberRoleChanged $event, Throwable $exception): void
    {
        Log::error('Team role change email failed', [
            'user_id' => $event->member->uuid,
            'organization' => $event->organizationName,
            'exception' => $exception::class,
            'message' => $exception->getMessage(),
        ]);
    }
}
