<?php

namespace App\DTOs\Invoice;

use App\Http\Requests\Invoice\StoreInvoiceRequest;

final readonly class CreateInvoiceDTO
{
    /**
     * @param  InvoiceLineDTO[]  $lines
     * @param  TaxTotalDTO[]  $taxTotals
     * @param  AllowanceChargeDTO[]  $allowanceCharges
     * @param  PaymentMeansDTO[]  $paymentMeans
     * @param  DocReferenceDTO[]  $docReferences
     */
    public function __construct(
        public int $organization_id,
        public int $customer_id,
        public string $invoice_number,
        public string $invoice_type_code,
        public string $issue_date,
        public ?string $due_date,
        public ?string $issue_time,
        public string $document_currency_code,
        public ?string $tax_currency_code,
        public ?string $payment_status,
        public ?string $note,
        public ?string $tax_point_date,
        public ?string $accounting_cost,
        public ?string $buyer_reference,
        public ?string $order_reference,
        public ?string $actual_delivery_date,
        public ?string $delivery_period_start,
        public ?string $delivery_period_end,
        public ?string $payment_terms_note,
        public LegalMonetaryTotalDTO $legal_monetary_total,
        public array $lines,
        public array $tax_totals = [],
        public array $allowance_charges = [],
        public array $payment_means = [],
        public array $doc_references = [],
        public int $created_by = 0,
    ) {}

    public static function fromRequest(StoreInvoiceRequest $request): self
    {
        $validated = $request->validated();

        $legal_monetary_total = new LegalMonetaryTotalDTO(...$validated['legal_monetary_total']);

        $lines = array_map(fn ($line) => new InvoiceLineDTO(
            line_id: (string) ($line['line_id'] ?? 1),
            invoiced_quantity: (float) ($line['invoiced_quantity'] ?? 1),
            line_extension_amount: (float) (($line['invoiced_quantity'] ?? 1) * ($line['price_amount'] ?? 0)),
            item_name: (string) ($line['item_name'] ?? 'Item'),
            price_amount: (float) ($line['price_amount'] ?? 0),
            tax_category_id: (string) ($line['tax_category_id'] ?? 'STANDARD_VAT'),
            tax_percent: (float) ($line['tax_percent'] ?? 7.5),
            unit_code: (string) ($line['price_unit'] ?? 'EA'),
            item_description: (string) ($line['item_description'] ?? null),
            hs_code: (string) ($line['hsn_code'] ?? null),
            item_category: (string) ($line['product_category'] ?? null),
            price_base_quantity: (float) ($line['base_quantity'] ?? 1),
        ), $validated['lines']);

        $tax_totals = array_map(fn ($tax) => new TaxTotalDTO(
            tax_amount: (float) ($tax['tax_amount'] ?? 0),
            tax_category_id: (string) ($tax['tax_category_id'] ?? 'STANDARD_VAT'),
            tax_percent: (float) ($tax['tax_percent'] ?? 7.5),
            taxable_amount: (float) ($tax['taxable_amount'] ?? null),
        ), $validated['tax_totals'] ?? []);

        return new self(
            organization_id: $request->user()->current_organization_id,
            customer_id: $validated['customer_id'],
            invoice_number: $validated['invoice_number'],
            invoice_type_code: $validated['invoice_type_code'] ?? '380',
            issue_date: $validated['issue_date'],
            due_date: $validated['due_date'] ?? null,
            issue_time: $validated['issue_time'] ?? null,
            document_currency_code: $validated['document_currency_code'] ?? 'NGN',
            tax_currency_code: $validated['tax_currency_code'] ?? null,
            payment_status: $validated['payment_status'] ?? 'PENDING',
            note: $validated['note'] ?? null,
            tax_point_date: $validated['tax_point_date'] ?? null,
            accounting_cost: $validated['accounting_cost'] ?? null,
            buyer_reference: $validated['buyer_reference'] ?? null,
            order_reference: $validated['order_reference'] ?? null,
            actual_delivery_date: $validated['actual_delivery_date'] ?? null,
            delivery_period_start: $validated['delivery_period_start'] ?? null,
            delivery_period_end: $validated['delivery_period_end'] ?? null,
            payment_terms_note: $validated['payment_terms_note'] ?? null,
            legal_monetary_total: $legal_monetary_total,
            lines: $lines,
            tax_totals: $tax_totals,
            created_by: $request->user()->id,
        );
    }

    public function toInvoiceArray(): array
    {
        return [
            'organization_id' => $this->organization_id,
            'customer_id' => $this->customer_id,
            'created_by' => $this->created_by,
            'invoice_number' => $this->invoice_number,
            'invoice_type_code' => $this->invoice_type_code,
            'issue_date' => $this->issue_date,
            'due_date' => $this->due_date,
            'issue_time' => $this->issue_time,
            'document_currency_code' => $this->document_currency_code,
            'tax_currency_code' => $this->tax_currency_code,
            'note' => $this->note,
            'tax_point_date' => $this->tax_point_date,
            'accounting_cost' => $this->accounting_cost,
            'buyer_reference' => $this->buyer_reference,
            'order_reference' => $this->order_reference,
            'actual_delivery_date' => $this->actual_delivery_date,
            'delivery_period_start' => $this->delivery_period_start,
            'delivery_period_end' => $this->delivery_period_end,
            'payment_terms_note' => $this->payment_terms_note,
            'line_extension_amount' => $this->legal_monetary_total->line_extension_amount,
            'tax_exclusive_amount' => $this->legal_monetary_total->tax_exclusive_amount,
            'tax_inclusive_amount' => $this->legal_monetary_total->tax_inclusive_amount,
            'payable_amount' => $this->legal_monetary_total->payable_amount,
            'allowance_total_amount' => $this->legal_monetary_total->allowance_total_amount,
            'charge_total_amount' => $this->legal_monetary_total->charge_total_amount,
            'prepaid_amount' => $this->legal_monetary_total->prepaid_amount,
            'payable_rounding_amount' => $this->legal_monetary_total->payable_rounding_amount,
        ];
    }
}
