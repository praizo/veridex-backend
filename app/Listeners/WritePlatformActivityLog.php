<?php

namespace App\Listeners;

use App\Events\PlatformInvoiceUpdated;
use App\Events\PlatformOrganizationUpdated;
use App\Events\PlatformUserUpdated;
use App\Services\ActivityLog\ActivityLogService;

class WritePlatformActivityLog
{
    public function __construct(
        private readonly ActivityLogService $activityLog,
    ) {}

    public function handle(object $event): void
    {
        if ($event instanceof PlatformOrganizationUpdated) {
            $this->activityLog->log(
                $event->actor,
                'platform.organization.updated',
                $event->organization,
                "Platform admin updated organization {$event->organization->name}.",
                ['before' => $event->before, 'after' => $event->after, 'reason' => $event->reason],
            );
        }

        if ($event instanceof PlatformUserUpdated) {
            $this->activityLog->log(
                $event->actor,
                'platform.user.updated',
                $event->user,
                "Platform admin updated user {$event->user->email}.",
                ['before' => $event->before, 'after' => $event->after, 'reason' => $event->reason],
            );
        }

        if ($event instanceof PlatformInvoiceUpdated) {
            $this->activityLog->log(
                $event->actor,
                'platform.invoice.updated',
                $event->invoice,
                "Platform admin updated invoice {$event->invoice->invoice_number}.",
                ['before' => $event->before, 'after' => $event->after, 'reason' => $event->reason],
            );
        }
    }
}
