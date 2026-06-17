<?php

namespace App\Services\Invoice;

use App\DTOs\Invoice\UpdateInvoicePaymentStatusDTO;
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
        $fiscalizedStatuses = ['signed', 'pending_transmit', 'transmit_failed', 'transmitted', 'confirmed'];
        $currentStatus = $invoice->status->value ?? $invoice->status;

        if (in_array($currentStatus, $fiscalizedStatuses, true) && $dto->paymentStatus !== 'PENDING') {
            $this->nrsService->updatePayment($invoice, $dto->paymentStatus, $dto->reference);
        }

        $invoice->update(['payment_status' => $dto->paymentStatus]);

        InvoicePaymentStatusUpdated::dispatch($invoice->fresh(), $actor, $dto->paymentStatus, $dto->reference);

        return $invoice->fresh();
    }
}
