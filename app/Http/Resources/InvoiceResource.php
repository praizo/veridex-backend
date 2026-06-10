<?php

namespace App\Http\Resources;

use BackedEnum;
use Illuminate\Http\Resources\Json\JsonResource;

class InvoiceResource extends JsonResource
{
    private function enumValue(mixed $value): mixed
    {
        return $value instanceof BackedEnum ? $value->value : $value;
    }

    private function statusValue(): string
    {
        return (string) $this->enumValue($this->status);
    }

    private function statusLabel(string $status): string
    {
        if ($status === 'transmit_failed') {
            return 'Signed - Transmission failed';
        }

        return [
            'draft' => 'Draft',
            'pending_validation' => 'Validation pending',
            'validated' => 'Validated',
            'validation_failed' => 'Validation failed',
            'pending_signing' => 'Signing pending',
            'sign_failed' => 'Signing failed',
            'signed' => 'Signed',
            'pending_transmit' => 'Transmission pending',
            'transmit_failed' => 'Signed · Transmission failed',
            'transmitted' => 'Transmitted',
            'confirmed' => 'Confirmed',
            'cancelled' => 'Cancelled',
        ][$status] ?? str_replace('_', ' ', $status);
    }

    private function lifecycle(string $status): array
    {
        $validatedStatuses = ['validated', 'pending_signing', 'sign_failed', 'signed', 'pending_transmit', 'transmit_failed', 'transmitted', 'confirmed'];
        $signedStatuses = ['signed', 'pending_transmit', 'transmit_failed', 'transmitted', 'confirmed'];
        $transmittedStatuses = ['transmitted', 'confirmed'];

        return [
            'validated' => in_array($status, $validatedStatuses, true),
            'signed' => in_array($status, $signedStatuses, true),
            'transmitted' => in_array($status, $transmittedStatuses, true),
            'confirmed' => $status === 'confirmed',
            'current_blocker' => in_array($status, ['validation_failed', 'sign_failed', 'transmit_failed'], true)
                ? $status
                : null,
        ];
    }

    public function toArray($request): array
    {
        $status = $this->statusValue();

        return [
            'id' => $this->uuid,
            'invoice_number' => $this->invoice_number,
            'invoice_type_code' => $this->invoice_type_code,
            'invoice_kind' => $this->invoice_kind,
            'irn' => $this->irn,
            'status' => $status,
            'status_label' => $this->statusLabel($status),
            'lifecycle' => $this->lifecycle($status),
            'payment_status' => $this->enumValue($this->payment_status),
            'issue_date' => $this->issue_date?->format('Y-m-d'),
            'due_date' => $this->due_date?->format('Y-m-d'),
            'document_currency_code' => $this->document_currency_code,

            // Financials
            'line_extension_amount' => (float) $this->line_extension_amount,
            'tax_exclusive_amount' => (float) $this->tax_exclusive_amount,
            'tax_inclusive_amount' => (float) $this->tax_inclusive_amount,
            'payable_amount' => (float) $this->payable_amount,
            'seller_snapshot' => $this->seller_snapshot,
            'buyer_snapshot' => $this->buyer_snapshot,
            'line_snapshot' => $this->line_snapshot,
            'tax_snapshot' => $this->tax_snapshot,
            'pdf_hash' => $this->pdf_hash,
            'xml_hash' => $this->xml_hash,
            'official_pdf_hash' => $this->official_pdf_hash,
            'official_xml_hash' => $this->official_xml_hash,
            'has_official_pdf' => (bool) $this->official_pdf_path,
            'has_official_xml' => (bool) $this->official_xml_path,

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

            'qr_code_url' => $this->irn && in_array($status, ['signed', 'pending_transmit', 'transmit_failed', 'transmitted', 'confirmed'], true)
                ? 'https://api.qrserver.com/v1/create-qr-code/?size=150x150&data='.urlencode('https://nrs.firs.gov.ng/verify/'.$this->irn)
                : null,

            'created_at' => $this->created_at,
            'updated_at' => $this->updated_at,
        ];
    }
}
