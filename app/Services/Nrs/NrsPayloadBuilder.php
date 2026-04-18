<?php

namespace App\Services\Nrs;

use App\Models\Invoice;
 
/**
 * Transforms Veridex internal models into NRS-compliant JSON payloads.
 */
class NrsPayloadBuilder
{
    /**
     * Build the full payload for the 'Validate' or 'Sign' NRS endpoints.
     */
    public function buildFullInvoicePayload(Invoice $invoice): array
    {
        $organization = $invoice->organization;
        $customer = $invoice->customer;

        $payload = [
            'business_id'             => (string) $organization->tin,
            'irn'                     => $invoice->irn,
            'issue_date'              => $invoice->issue_date->format('Y-m-d'),
            'invoice_type_code'       => (string) $invoice->invoice_type_code,
            'document_currency_code'  => $invoice->document_currency_code,
            
            'accounting_supplier_party' => $this->buildPartyData($organization),
            'accounting_customer_party' => $this->buildPartyData($customer, true),
            
            'legal_monetary_total'    => [
                'line_extension_amount' => (float) $invoice->line_extension_amount,
                'tax_exclusive_amount'  => (float) $invoice->tax_exclusive_amount,
                'tax_inclusive_amount'  => (float) $invoice->tax_inclusive_amount,
                'payable_amount'        => (float) $invoice->payable_amount,
            ],
            
            'invoice_line' => $invoice->lines->map(fn($line) => [
                'hsn_code'                 => $line->hs_code ?? 'CC-001',
                'product_category'         => $line->item_category ?? 'General Items',
                'invoiced_quantity'        => (float) $line->invoiced_quantity,
                'line_extension_amount'    => (float) $line->line_extension_amount,
                'item' => [
                    'name'                         => $line->item_name,
                    'description'                  => $line->item_description,
                    'sellers_item_identification' => $line->sellers_item_identification,
                ],
                'price' => [
                    'price_amount'  => (float) $line->price_amount,
                    'base_quantity' => (float) ($line->base_quantity ?? 1),
                    'price_unit'    => $line->price_unit ?? 'UNIT',
                ],
                // These are conditional in the UBL standard
                'discount_amount' => $line->discount_amount ? (float) $line->discount_amount : null,
                'fee_amount'      => $line->fee_amount ? (float) $line->fee_amount : null,
            ])->filter()->values()->toArray(),
        ];

        // Allowance Charges (Root level)
        if ($invoice->allowanceCharges->isNotEmpty()) {
            $payload['allowance_charge'] = $invoice->allowanceCharges->map(fn($ac) => [
                'charge_indicator' => (bool) $ac->charge_indicator,
                'amount'           => (float) $ac->amount,
            ])->toArray();
        }

        // Optional Top-level fields
        if ($invoice->due_date) {
            $payload['due_date'] = $invoice->due_date->format('Y-m-d');
        }
        if ($invoice->issue_time) {
            $payload['issue_time'] = $invoice->issue_time;
        }
        if ($invoice->note) {
            $payload['note'] = $invoice->note;
        }

        // Relational Data: Tax Totals
        if ($invoice->taxTotals->isNotEmpty()) {
            $payload['tax_total'] = $invoice->taxTotals->map(fn($t) => [
                'tax_amount' => (float) $t->tax_amount,
                'tax_subtotal' => [[
                    'taxable_amount' => (float) $t->taxable_amount,
                    'tax_amount'     => (float) $t->tax_amount,
                    'tax_category'   => [
                        'id'      => $this->mapTaxCategoryId($t->tax_category_id),
                        'percent' => (float) $t->tax_percent,
                    ]
                ]]
            ])->toArray();
        }

        // Relational Data: Payment Means
        if ($invoice->paymentMeans->isNotEmpty()) {
            $payload['payment_means'] = $invoice->paymentMeans->map(fn($p) => [
                'payment_means_code' => $p->payment_means_code,
                'payment_due_date'   => $p->payment_due_date ? $p->payment_due_date->format('Y-m-d') : null,
            ])->toArray();
        }

        return array_filter($payload, fn($value) => !is_null($value));
    }

    /**
     * Build Party Data for Supplier or Customer.
     */
    protected function buildPartyData($entity, bool $isCustomer = false): array
    {
        // For Organizations, $entity is Organization model
        // For Customers, $entity is Customer model
        
        return [
            'party_name'           => $entity->name,
            'tin'                  => $isCustomer ? $entity->tin : ($entity->tin ?? 'TIN-NOT-SET'),
            'email'                 => $entity->email,
            'telephone'             => $entity->telephone ?? $entity->phone ?? null,
            'business_description'  => $entity->business_description ?? null,
            'postal_address'        => [
                'street_name' => $entity->street_name ?? 'N/A',
                'city_name'   => $entity->city_name ?? 'N/A',
                'postal_zone' => $entity->postal_zone ?? 'N/A',
                'country'     => $entity->country_code ?? 'NG',
            ]
        ];
    }
    /**
     * Map internal tax category IDs to FIRS-compliant codes.
     */
    protected function mapTaxCategoryId(string $id): string
    {
        return match ($id) {
            'STANDARD_VAT' => 'STANDARD_VAT',
            'ZERO_VAT', 'EXEMPT_VAT' => 'ZERO_VAT',
            'S' => 'STANDARD_VAT',
            default => $id,
        };
    }
}