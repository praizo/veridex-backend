<?php

namespace App\Listeners;

use App\Events\InvoicePaymentStatusUpdated;
use App\Services\ActivityLog\ActivityLogService;

class WriteInvoicePaymentActivityLog
{
    public function __construct(
        private readonly ActivityLogService $activityLog,
    ) {}

    public function handle(InvoicePaymentStatusUpdated $event): void
    {
        $this->activityLog->log(
            $event->actor,
            'PAYMENT_STATUS_UPDATE',
            $event->invoice,
            "Marked invoice as {$event->paymentStatus}",
            $event->reference ? ['reference' => $event->reference] : null,
        );
    }
}
