<?php

namespace App\Http\Resources;

use Illuminate\Http\Resources\Json\JsonResource;

class InvoiceResource extends JsonResource
{
    public function toArray($request): array
    {
        return [
            'id' => $this->id,
            'invoice_number' => $this->invoice_number,
            'invoice_type_code' => $this->invoice_type_code,
            'irn' => $this->irn,
            'status' => $this->status,
            'payment_status' => $this->payment_status,
            'issue_date' => $this->issue_date?->format('Y-m-d'),
            'due_date' => $this->due_date?->format('Y-m-d'),

            // Financials
            'line_extension_amount' => (float) $this->line_extension_amount,
            'tax_exclusive_amount' => (float) $this->tax_exclusive_amount,
            'tax_inclusive_amount' => (float) $this->tax_inclusive_amount,
            'payable_amount' => (float) $this->payable_amount,

            // Relationships
            'organization' => $this->whenLoaded('organization'),
            'customer' => $this->whenLoaded('customer'),
            'lines' => $this->whenLoaded('lines'),
            'tax_totals' => $this->whenLoaded('taxTotals'),
            'payment_means' => $this->whenLoaded('paymentMeans'),
            'nrs_submissions' => $this->whenLoaded('nrsSubmissions'),
            'state_transitions' => $this->whenLoaded('stateTransitions', function () {
                return $this->stateTransitions->sortByDesc('created_at');
            }),

            'qr_code_url' => $this->irn
                ? 'https://api.qrserver.com/v1/create-qr-code/?size=150x150&data='.urlencode('https://nrs.firs.gov.ng/verify/'.$this->irn)
                : null,

            'created_at' => $this->created_at,
            'updated_at' => $this->updated_at,
        ];
    }
}
