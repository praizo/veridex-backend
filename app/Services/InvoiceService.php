<?php

namespace App\Services;

use App\Models\Invoice;
use App\Models\InvoiceLine;
use App\Models\InvoiceTaxTotal;
use App\Models\InvoicePaymentMeans;
use App\DTOs\Invoice\CreateInvoiceDTO;
use App\Enums\InvoiceStatus;
use Illuminate\Support\Facades\DB;
use App\Services\ActivityLogService;
use App\Enums\ActivityAction;

class InvoiceService
{
    public function __construct(
        protected ActivityLogService $activityLog,
        protected InvoiceStateService $stateService,
        protected \App\Services\Nrs\NrsResourceService $resourceService
    ) {}

    /**
     * Create a full invoice with all relationships.
     */
    public function createInvoice(CreateInvoiceDTO $dto): Invoice
    {
        // 0. Validate Compliance Data against live FIRS Resources
        $this->validateTaxCompliance($dto);

        return DB::transaction(function () use ($dto) {
            // 1. Create the root Invoice record
            $invoiceData = $dto->toInvoiceArray();
            $invoiceData['status'] = InvoiceStatus::DRAFT;
            
            $invoice = Invoice::create($invoiceData);

            // 2. Create Invoice Lines
            foreach ($dto->lines as $lineDto) {
                $invoice->lines()->create($lineDto->toArray());
            }

            // 3. Create Tax Totals
            foreach ($dto->tax_totals as $taxDto) {
                $invoice->taxTotals()->create($taxDto->toArray());
            }

            // 4. Create Payment Means
            foreach ($dto->payment_means as $payDto) {
                $invoice->paymentMeans()->create($payDto->toArray());
            }

            // 5. Create Allowance Charges
            foreach ($dto->allowance_charges as $allowDto) {
                $invoice->allowanceCharges()->create($allowDto->toArray());
            }

            // 6. Create Document References
            foreach ($dto->doc_references as $docDto) {
                $invoice->docReferences()->create($docDto->toArray());
            }

            // Log activity
            $this->activityLog->log(
                auth()->user(),
                \App\Enums\ActivityAction::INVOICE_CREATED->value,
                $invoice,
                "Invoice #{$invoice->invoice_number} created as draft."
            );

            return $invoice;
        });
    }

    /**
     * Ensure that the invoice data matches the official FIRS resource data.
     */
    protected function validateTaxCompliance(CreateInvoiceDTO $dto): void
    {
        $taxCategories = $this->resourceService->getTaxCategories();
        
        if (empty($taxCategories)) {
            // If the FIRS API is down and cache is empty, we might want to log a warning
            // but usually we should have a fallback or a cached version.
            return;
        }

        // Create a lookup map for easy validation: 'STANDARD_VAT' => 7.5
        $lookup = [];
        foreach ($taxCategories as $cat) {
            $code = $cat['code'] ?? null;
            $percent = $cat['percent'] ?? null;
            if ($code) {
                $lookup[$code] = $percent === 'Not Available' || $percent === '' ? null : (float) $percent;
            }
        }

        // Validate each line item's tax
        foreach ($dto->lines as $line) {
            $code = $line->tax_category_id;
            
            if (!isset($lookup[$code])) {
                throw new \App\Exceptions\NrsApiException("Invalid Tax Category ID: {$code}. Please use a valid FIRS category.");
            }

            $officialPercent = $lookup[$code];
            if ($officialPercent !== null && abs($line->tax_percent - $officialPercent) > 0.001) {
                throw new \App\Exceptions\NrsApiException("Tax Rate Mismatch: Category {$code} requires {$officialPercent}%, but {$line->tax_percent}% was provided.");
            }
        }
    }

    /**
     * Find an invoice by ID, ensuring organization scope.
     */
    public function findById(int $id, int $organizationId): ?Invoice
    {
        return Invoice::where('id', $id)
            ->where('organization_id', $organizationId)
            ->first();
    }
}
