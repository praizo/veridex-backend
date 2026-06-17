<?php

namespace App\Events;

use App\Models\Invoice;
use App\Models\User;
use Illuminate\Foundation\Events\Dispatchable;

class InvoicePaymentStatusUpdated
{
    use Dispatchable;

    public function __construct(
        public readonly Invoice $invoice,
        public readonly ?User $actor,
        public readonly string $paymentStatus,
        public readonly ?string $reference = null,
    ) {}
}
