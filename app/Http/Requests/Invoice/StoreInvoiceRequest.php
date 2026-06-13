<?php

namespace App\Http\Requests\Invoice;

use App\Models\Customer;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreInvoiceRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true; // We use policies typically, but returning true for now
    }

    protected function prepareForValidation()
    {
        if ($this->has('customer_id') && is_string($this->customer_id)) {
            $customer = Customer::where('uuid', $this->customer_id)
                ->where('organization_id', $this->user()->current_organization_id)
                ->first();

            if ($customer) {
                $this->merge(['customer_id' => $customer->id]);
            }
        }
    }

    public function rules(): array
    {
        return [
            'customer_id' => [
                'required',
                Rule::exists('customers', 'id')
                    ->where('organization_id', $this->user()->current_organization_id),
            ],
            'invoice_number' => [
                'nullable',
                'string',
                'max:255',
            ],
            'invoice_type_code' => ['required', 'string', 'in:380,381,383,386,396'],
            'invoice_kind' => ['nullable', 'string', 'in:B2B,B2C,B2G'],
            'issue_date' => ['required', 'date'],
            'due_date' => ['nullable', 'date', 'after_or_equal:issue_date'],
            'issue_time' => ['nullable', 'string'], // Time format
            'document_currency_code' => ['required', 'string', 'size:3'],
            'tax_currency_code' => ['nullable', 'string', 'size:3'],
            'payment_status' => ['nullable', 'string', 'in:PENDING,PAID,PARTIAL,REJECTED'],
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
            'legal_monetary_total' => ['nullable', 'array'],
            'legal_monetary_total.line_extension_amount' => ['nullable', 'numeric', 'min:0'],
            'legal_monetary_total.tax_exclusive_amount' => ['nullable', 'numeric', 'min:0'],
            'legal_monetary_total.tax_inclusive_amount' => ['nullable', 'numeric', 'min:0'],
            'legal_monetary_total.payable_amount' => ['nullable', 'numeric', 'min:0'],
            'legal_monetary_total.allowance_total_amount' => ['nullable', 'numeric', 'min:0'],
            'legal_monetary_total.charge_total_amount' => ['nullable', 'numeric', 'min:0'],
            'legal_monetary_total.prepaid_amount' => ['nullable', 'numeric', 'min:0'],
            'legal_monetary_total.payable_rounding_amount' => ['nullable', 'numeric', 'min:0'],

            // Lines
            'lines' => ['required', 'array', 'min:1'],
            'lines.*.line_id' => ['required', 'string'],
            'lines.*.item_type' => ['nullable', 'string', 'in:goods,service'],
            'lines.*.invoiced_quantity' => ['required', 'integer', 'min:1'],
            'lines.*.line_extension_amount' => ['required', 'numeric', 'min:0'],
            'lines.*.item_name' => ['required', 'string'],
            'lines.*.price_amount' => ['required', 'numeric', 'min:0'],
            'lines.*.unit_code' => ['nullable', 'string'],
            'lines.*.price_unit' => ['nullable', 'string'],
            'lines.*.item_description' => ['nullable', 'string'],
            'lines.*.item_standard_id' => ['nullable', 'string'],
            'lines.*.price_base_quantity' => ['nullable', 'numeric', 'min:1'],
            'lines.*.tax_category_id' => ['nullable', 'string'],
            'lines.*.tax_percent' => ['nullable', 'numeric', 'min:0'],
            'lines.*.tax_scheme_id' => ['nullable', 'string'],
            'lines.*.hsn_code' => ['nullable', 'string'],
            'lines.*.product_category' => ['nullable', 'string'],
            'lines.*.isic_code' => ['nullable', 'string'],
            'lines.*.service_category' => ['nullable', 'string'],

            // Tax Totals
            'tax_totals' => ['nullable', 'array'],
            'tax_totals.*.tax_amount' => ['required', 'numeric', 'min:0'],
            'tax_totals.*.taxable_amount' => ['nullable', 'numeric', 'min:0'],
            'tax_totals.*.tax_category_id' => ['nullable', 'string'],
            'tax_totals.*.tax_percent' => ['nullable', 'numeric', 'min:0'],
            'tax_totals.*.tax_scheme_id' => ['nullable', 'string'],

            // (Skipping detailed rules for allowanceCharges, paymentMeans, docReferences for brevity in Phase 1)
        ];
    }

    public function withValidator($validator): void
    {
        $validator->after(function ($validator) {
            foreach ($this->input('lines', []) as $index => $line) {
                $itemType = $line['item_type'] ?? 'goods';

                if ($itemType === 'goods') {
                    if (empty($line['hsn_code'])) {
                        $validator->errors()->add("lines.{$index}.hsn_code", 'HSN code is required for goods.');
                    }

                    if (empty($line['product_category'])) {
                        $validator->errors()->add("lines.{$index}.product_category", 'Product category is required for goods.');
                    }
                }

                if ($itemType === 'service') {
                    if (empty($line['isic_code'])) {
                        $validator->errors()->add("lines.{$index}.isic_code", 'ISIC code is required for services.');
                    }

                    if (empty($line['service_category'])) {
                        $validator->errors()->add("lines.{$index}.service_category", 'Service category is required for services.');
                    }
                }
            }
        });
    }
}
