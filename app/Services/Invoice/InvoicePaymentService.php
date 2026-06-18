<?php

namespace App\Services\Invoice;

use App\DTOs\Invoice\UpdateInvoicePaymentStatusDTO;
use App\Enums\InvoiceStatus;
use App\Events\InvoicePaymentStatusUpdated;
use App\Models\Invoice;
use App\Models\User;
use App\Services\Nrs\NrsInvoiceService;

class InvoicePaymentService
{
    public function __construct(
        private readonly NrsInvoiceService $nrsService,
    ) {}

    public function updatePaymentStatus(Invoice $invoice, UpdateInvoicePaymentStatusDTO $dto, ?User $actor): Invoice
    {
        $status = $invoice->status instanceof InvoiceStatus
            ? $invoice->status
            : InvoiceStatus::tryFrom((string) $invoice->status);

        if ($status?->isFiscalized() && $dto->paymentStatus !== 'PENDING') {
            $this->nrsService->updatePayment($invoice, $dto->paymentStatus, $dto->reference);
        }

        $invoice->forceFill(['payment_status' => $dto->paymentStatus])->save();

        InvoicePaymentStatusUpdated::dispatch($invoice->fresh(), $actor, $dto->paymentStatus, $dto->reference);

        return $invoice->fresh();
    }
}
