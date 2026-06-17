<?php

namespace App\DTOs\Invoice;

use App\Http\Requests\Invoice\UpdateInvoicePaymentStatusRequest;

final readonly class UpdateInvoicePaymentStatusDTO
{
    public function __construct(
        public string $paymentStatus,
        public ?string $reference = null,
    ) {}

    public static function fromRequest(UpdateInvoicePaymentStatusRequest $request): self
    {
        $validated = $request->validated();

        return new self(
            paymentStatus: $validated['payment_status'],
            reference: $validated['reference'] ?? null,
        );
    }
}
