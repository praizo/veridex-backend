<?php

namespace App\Services;

use App\DTOs\Invoice\CreateInvoiceDTO;
use App\Enums\ActivityAction;
use App\Enums\InvoiceStatus;
use App\Exceptions\NrsApiException;
use App\Models\Customer;
use App\Models\Invoice;
use App\Models\InvoiceSequence;
use App\Services\Nrs\NrsResourceService;
use Illuminate\Support\Facades\DB;

class InvoiceService
{
    public function __construct(
        protected ActivityLogService $activityLog,
        protected InvoiceStateService $stateService,
        protected NrsResourceService $resourceService
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
            $calculated = $this->calculateAuthoritativeAmounts($dto);
            $invoiceData = $dto->toInvoiceArray();
            $invoiceData = array_merge($invoiceData, $calculated['totals']);
            $invoiceData['status'] = InvoiceStatus::DRAFT;
            $invoiceData['invoice_number'] = $this->generateNextNumber($dto->organization_id);

            if (empty($invoiceData['invoice_kind'])) {
                $customer = Customer::find($dto->customer_id);
                if ($customer) {
                    $invoiceData['invoice_kind'] = match ($customer->type) {
                        'individual' => 'B2C',
                        'government' => 'B2G',
                        default => 'B2B',
                    };
                } else {
                    $invoiceData['invoice_kind'] = 'B2B';
                }
            }

            $invoice = Invoice::create($invoiceData);

            // 2. Create Invoice Lines
            foreach ($calculated['lines'] as $line) {
                $invoice->lines()->create($line);
            }

            // 3. Create Tax Totals
            foreach ($calculated['tax_totals'] as $taxTotal) {
                $invoice->taxTotals()->create($taxTotal);
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

            // Log activity status
            $this->activityLog->log(
                auth()->user(),
                ActivityAction::INVOICE_CREATED->value,
                $invoice,
                "Invoice #{$invoice->invoice_number} created as draft."
            );

            return $invoice;
        });
    }

    /**
     * Update an editable draft invoice and recalculate all monetary amounts.
     */
    public function updateDraftInvoice(Invoice $invoice, CreateInvoiceDTO $dto): Invoice
    {
        $status = $invoice->status?->value ?? $invoice->status;
        if (! in_array($status, ['draft', 'validation_failed'], true)) {
            throw new InvoiceStateException('Only draft invoices can be edited.');
        }

        $this->validateTaxCompliance($dto);

        return DB::transaction(function () use ($invoice, $dto) {
            $calculated = $this->calculateAuthoritativeAmounts($dto);
            $invoiceData = array_merge($dto->toInvoiceArray(), $calculated['totals']);

            unset($invoiceData['organization_id'], $invoiceData['created_by'], $invoiceData['invoice_number']);
            $invoiceData['status'] = InvoiceStatus::DRAFT;

            $invoice->update($invoiceData);
            $invoice->lines()->delete();
            $invoice->taxTotals()->delete();
            $invoice->paymentMeans()->delete();
            $invoice->allowanceCharges()->delete();
            $invoice->docReferences()->delete();

            foreach ($calculated['lines'] as $line) {
                $invoice->lines()->create($line);
            }

            foreach ($calculated['tax_totals'] as $taxTotal) {
                $invoice->taxTotals()->create($taxTotal);
            }

            foreach ($dto->payment_means as $payDto) {
                $invoice->paymentMeans()->create($payDto->toArray());
            }

            foreach ($dto->allowance_charges as $allowDto) {
                $invoice->allowanceCharges()->create($allowDto->toArray());
            }

            foreach ($dto->doc_references as $docDto) {
                $invoice->docReferences()->create($docDto->toArray());
            }

            $this->activityLog->log(
                auth()->user(),
                ActivityAction::UPDATED->value,
                $invoice,
                "Draft invoice #{$invoice->invoice_number} updated."
            );

            return $invoice->fresh(['customer', 'lines', 'organization', 'taxTotals', 'paymentMeans', 'stateTransitions']);
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

            if (! isset($lookup[$code])) {
                throw new NrsApiException("Invalid Tax Category ID: {$code}. Please use a valid FIRS category.");
            }

            $officialPercent = $lookup[$code];
            if ($officialPercent !== null && abs($line->tax_percent - $officialPercent) > 0.001) {
                throw new NrsApiException("Tax Rate Mismatch: Category {$code} requires {$officialPercent}%, but {$line->tax_percent}% was provided.");
            }
        }
    }

    /**
     * Recalculate invoice money server-side so client totals remain previews only.
     */
    protected function calculateAuthoritativeAmounts(CreateInvoiceDTO $dto): array
    {
        $lineExtensionAmount = 0.0;
        $taxGroups = [];
        $lines = [];

        foreach ($dto->lines as $lineDto) {
            $line = $lineDto->toArray();
            $quantity = (float) $lineDto->invoiced_quantity;
            $price = (float) $lineDto->price_amount;
            $lineTotal = $this->roundMoney($quantity * $price);
            $taxPercent = (float) ($lineDto->tax_percent ?? 0);
            $taxAmount = $this->roundMoney($lineTotal * ($taxPercent / 100));

            $line['line_extension_amount'] = $lineTotal;
            $lines[] = $line;

            $lineExtensionAmount += $lineTotal;

            $taxKey = implode('|', [
                $lineDto->tax_category_id ?? '',
                (string) $taxPercent,
                $lineDto->tax_scheme_id ?? '',
            ]);

            if (! isset($taxGroups[$taxKey])) {
                $taxGroups[$taxKey] = [
                    'tax_amount' => 0.0,
                    'taxable_amount' => 0.0,
                    'tax_category_id' => $lineDto->tax_category_id,
                    'tax_percent' => $taxPercent,
                    'tax_scheme_id' => $lineDto->tax_scheme_id,
                ];
            }

            $taxGroups[$taxKey]['taxable_amount'] += $lineTotal;
            $taxGroups[$taxKey]['tax_amount'] += $taxAmount;
        }

        $allowanceTotal = 0.0;
        $chargeTotal = 0.0;
        foreach ($dto->allowance_charges as $allowanceCharge) {
            if ($allowanceCharge->charge_indicator) {
                $chargeTotal += (float) $allowanceCharge->amount;
            } else {
                $allowanceTotal += (float) $allowanceCharge->amount;
            }
        }

        $lineExtensionAmount = $this->roundMoney($lineExtensionAmount);
        $allowanceTotal = $this->roundMoney($allowanceTotal);
        $chargeTotal = $this->roundMoney($chargeTotal);
        $prepaidAmount = $this->roundMoney($dto->legal_monetary_total->prepaid_amount ?? 0);
        $roundingAmount = $this->roundMoney($dto->legal_monetary_total->payable_rounding_amount ?? 0);
        $taxExclusiveAmount = $this->roundMoney($lineExtensionAmount - $allowanceTotal + $chargeTotal);

        $taxTotals = array_map(fn ($taxTotal) => [
            'tax_amount' => $this->roundMoney($taxTotal['tax_amount']),
            'taxable_amount' => $this->roundMoney($taxTotal['taxable_amount']),
            'tax_category_id' => $taxTotal['tax_category_id'],
            'tax_percent' => $taxTotal['tax_percent'],
            'tax_scheme_id' => $taxTotal['tax_scheme_id'],
        ], array_values($taxGroups));

        $totalTaxAmount = array_sum(array_column($taxTotals, 'tax_amount'));
        $taxInclusiveAmount = $this->roundMoney($taxExclusiveAmount + $totalTaxAmount);
        $payableAmount = $this->roundMoney($taxInclusiveAmount - $prepaidAmount + $roundingAmount);

        return [
            'lines' => $lines,
            'tax_totals' => $taxTotals,
            'totals' => [
                'line_extension_amount' => $lineExtensionAmount,
                'tax_exclusive_amount' => $taxExclusiveAmount,
                'tax_inclusive_amount' => $taxInclusiveAmount,
                'payable_amount' => $payableAmount,
                'allowance_total_amount' => $allowanceTotal,
                'charge_total_amount' => $chargeTotal,
                'prepaid_amount' => $prepaidAmount,
                'payable_rounding_amount' => $roundingAmount,
            ],
        ];
    }

    protected function roundMoney(float $amount): float
    {
        return round($amount, 2);
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

    /**
     * Generate the next sequential invoice number for the organization.
     */
    public function generateNextNumber(int $orgId, string $prefix = 'INV'): string
    {
        $period = now()->format('Y');

        $sequence = InvoiceSequence::where('organization_id', $orgId)
            ->where('prefix', $prefix)
            ->where('period', $period)
            ->lockForUpdate()
            ->first();

        if (! $sequence) {
            InvoiceSequence::create([
                'organization_id' => $orgId,
                'prefix' => $prefix,
                'period' => $period,
                'next_number' => 2,
            ]);
            $number = 1;
        } else {
            $number = $sequence->next_number;
            $sequence->increment('next_number');
        }

        $padded = str_pad((string) $number, 6, '0', STR_PAD_LEFT);

        return "{$prefix}-{$period}-{$padded}";
    }
}
