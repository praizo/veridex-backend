<?php

namespace App\Http\Requests\Invoice;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreInvoiceRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true; // We use policies typically, but returning true for now
    }

    public function rules(): array
    {
        return [
            'customer_id' => ['required', 'exists:customers,id'],
            'invoice_number' => [
                'required',
                'string',
                'max:255',
                Rule::unique('invoices')->where('organization_id', $this->user()->current_organization_id),
            ],
            'invoice_type_code' => ['required', 'string', 'in:380,381,383,386,396'],
            'issue_date' => ['required', 'date'],
            'due_date' => ['nullable', 'date'],
            'issue_time' => ['nullable', 'string'], // Time format
            'document_currency_code' => ['required', 'string', 'size:3'],
            'tax_currency_code' => ['nullable', 'string', 'size:3'],
            'payment_status' => ['nullable', 'string', 'in:PENDING,PAID,REJECTED'],
            'note' => ['nullable', 'string'],
            'tax_point_date' => ['nullable', 'date'],
            'accounting_cost' => ['nullable', 'string'],
            'buyer_reference' => ['nullable', 'string'],
            'order_reference' => ['nullable', 'string'],
            'actual_delivery_date' => ['nullable', 'date'],
            'delivery_period_start' => ['nullable', 'date'],
            'delivery_period_end' => ['nullable', 'date'],
            'payment_terms_note' => ['nullable', 'string'],

            // Legal Monetary Total
            'legal_monetary_total' => ['required', 'array'],
            'legal_monetary_total.line_extension_amount' => ['required', 'numeric'],
            'legal_monetary_total.tax_exclusive_amount' => ['required', 'numeric'],
            'legal_monetary_total.tax_inclusive_amount' => ['required', 'numeric'],
            'legal_monetary_total.payable_amount' => ['required', 'numeric'],
            'legal_monetary_total.allowance_total_amount' => ['nullable', 'numeric'],
            'legal_monetary_total.charge_total_amount' => ['nullable', 'numeric'],
            'legal_monetary_total.prepaid_amount' => ['nullable', 'numeric'],
            'legal_monetary_total.payable_rounding_amount' => ['nullable', 'numeric'],

            // Lines
            'lines' => ['required', 'array', 'min:1'],
            'lines.*.line_id' => ['required', 'string'],
            'lines.*.invoiced_quantity' => ['required', 'numeric'],
            'lines.*.line_extension_amount' => ['required', 'numeric'],
            'lines.*.item_name' => ['required', 'string'],
            'lines.*.price_amount' => ['required', 'numeric'],
            'lines.*.unit_code' => ['nullable', 'string'],
            'lines.*.item_description' => ['nullable', 'string'],
            'lines.*.item_standard_id' => ['nullable', 'string'],
            'lines.*.price_base_quantity' => ['nullable', 'numeric'],
            'lines.*.tax_category_id' => ['nullable', 'string'],
            'lines.*.tax_percent' => ['nullable', 'numeric'],
            'lines.*.tax_scheme_id' => ['nullable', 'string'],

            // Tax Totals
            'tax_totals' => ['nullable', 'array'],
            'tax_totals.*.tax_amount' => ['required', 'numeric'],
            'tax_totals.*.taxable_amount' => ['nullable', 'numeric'],
            'tax_totals.*.tax_category_id' => ['nullable', 'string'],
            'tax_totals.*.tax_percent' => ['nullable', 'numeric'],
            'tax_totals.*.tax_scheme_id' => ['nullable', 'string'],

            // (Skipping detailed rules for allowanceCharges, paymentMeans, docReferences for brevity in Phase 1)
        ];
    }
}
