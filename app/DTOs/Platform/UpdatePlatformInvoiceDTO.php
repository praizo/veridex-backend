<?php

namespace App\DTOs\Platform;

use App\Http\Requests\Platform\UpdatePlatformInvoiceRequest;

final readonly class UpdatePlatformInvoiceDTO
{
    public function __construct(
        public bool $hasStatus,
        public ?string $status,
        public bool $hasPaymentStatus,
        public ?string $paymentStatus,
        public string $reason,
    ) {}

    public static function fromRequest(UpdatePlatformInvoiceRequest $request): self
    {
        $validated = $request->validated();

        return new self(
            hasStatus: array_key_exists('status', $validated),
            status: $validated['status'] ?? null,
            hasPaymentStatus: array_key_exists('payment_status', $validated),
            paymentStatus: $validated['payment_status'] ?? null,
            reason: $validated['reason'],
        );
    }
}
