<?php

namespace App\Listeners;

use App\Events\AccountSecurityAlertRequested;
use App\Notifications\VeridexAlertNotification;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Support\Facades\Log;
use Throwable;

class SendAccountSecurityAlert implements ShouldQueue
{
    public int $tries = 3;

    public int $timeout = 30;

    public function backoff(): array
    {
        return [10, 30, 60];
    }

    public function handle(AccountSecurityAlertRequested $event): void
    {
        $event->user->notify(new VeridexAlertNotification(
            subject: $event->subject,
            heading: $event->heading,
            message: $event->message,
            details: $event->details,
            actionText: $event->actionText,
            actionUrl: $event->actionUrl,
            footer: $event->footer,
        ));
    }

    public function failed(AccountSecurityAlertRequested $event, Throwable $exception): void
    {
        Log::error('Account security alert email failed', [
            'user_id' => $event->user->uuid,
            'subject' => $event->subject,
            'exception' => $exception::class,
            'message' => $exception->getMessage(),
        ]);
    }
}
