<?php

namespace App\DTOs\Invoice;

// To be created if needed, otherwise generic usage

final readonly class UpdateInvoiceDTO
{
    // Minimal for now, as invoices are mostly immutable once submitted.
    public function __construct(
        public ?string $note = null,
        public ?string $paymentStatus = null,
    ) {}
}
