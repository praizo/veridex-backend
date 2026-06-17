<?php

namespace App\Events;

use App\Models\Invoice;
use App\Models\User;
use Illuminate\Foundation\Events\Dispatchable;

class PlatformInvoiceUpdated
{
    use Dispatchable;

    public function __construct(
        public readonly User $actor,
        public readonly Invoice $invoice,
        public readonly array $before,
        public readonly array $after,
        public readonly string $reason,
    ) {}
}
