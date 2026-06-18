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
            return 'Transmission failed';
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
        $validatedStatuses = ['validated', 'pending_signing', 'sign_failed', 'signed', 'pending_transmit', 'transmit_failed', 'transmitted'];
        $signedStatuses = ['signed', 'pending_transmit', 'transmit_failed', 'transmitted'];
        $transmittedStatuses = ['transmitted'];

        return [
            'validated' => in_array($status, $validatedStatuses, true),
            'signed' => in_array($status, $signedStatuses, true),
            'transmitted' => in_array($status, $transmittedStatuses, true),
            'confirmed' => false,
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
            'has_official_pdf' => (bool) $this->official_pdf_path,
            'has_official_xml' => (bool) $this->official_xml_path,

            // Relationships
            'organization' => $this->whenLoaded('organization', fn () => (new OrganizationResource($this->organization))->resolve($request)),
            'customer' => $this->whenLoaded('customer', fn () => (new CustomerResource($this->customer))->resolve($request)),
            'lines' => $this->whenLoaded('lines', fn () => $this->lines->map(fn ($line) => $this->linePayload($line))->values()),
            'tax_totals' => $this->whenLoaded('taxTotals', fn () => $this->taxTotals->map(fn ($taxTotal) => $this->taxTotalPayload($taxTotal))->values()),
            'payment_means' => $this->whenLoaded('paymentMeans', fn () => $this->paymentMeans->map(fn ($paymentMean) => $this->paymentMeanPayload($paymentMean))->values()),
            'nrs_submissions' => $this->whenLoaded('nrsSubmissions', fn () => $this->nrsSubmissions->map(fn ($submission) => $this->nrsSubmissionPayload($submission))->values()),
            'state_transitions' => $this->whenLoaded('stateTransitions', function () {
                return $this->stateTransitions
                    ->sortByDesc('id')
                    ->map(fn ($transition) => $this->stateTransitionPayload($transition))
                    ->values();
            }),

            'qr_code_url' => $this->irn && in_array($status, ['signed', 'pending_transmit', 'transmit_failed', 'transmitted', 'confirmed'], true)
                ? 'https://api.qrserver.com/v1/create-qr-code/?size=150x150&data='.urlencode('https://nrs.firs.gov.ng/verify/'.$this->irn)
                : null,

            'created_at' => $this->created_at,
            'updated_at' => $this->updated_at,
        ];
    }

    private function linePayload($line): array
    {
        return [
            'line_id' => $line->line_id,
            'item_type' => $line->item_type,
            'invoiced_quantity' => $line->invoiced_quantity,
            'unit_code' => $line->unit_code,
            'line_extension_amount' => $line->line_extension_amount,
            'item_name' => $line->item_name,
            'item_description' => $line->item_description,
            'hs_code' => $line->hs_code,
            'item_category' => $line->item_category,
            'item_standard_id' => $line->item_standard_id,
            'price_amount' => $line->price_amount,
            'price_base_quantity' => $line->price_base_quantity,
            'tax_category_id' => $line->tax_category_id,
            'tax_percent' => $line->tax_percent,
            'tax_scheme_id' => $line->tax_scheme_id,
            'isic_code' => $line->isic_code,
            'service_category' => $line->service_category,
        ];
    }

    private function taxTotalPayload($taxTotal): array
    {
        return [
            'tax_amount' => $taxTotal->tax_amount,
            'taxable_amount' => $taxTotal->taxable_amount,
            'tax_category_id' => $taxTotal->tax_category_id,
            'tax_percent' => $taxTotal->tax_percent,
            'tax_scheme_id' => $taxTotal->tax_scheme_id,
        ];
    }

    private function paymentMeanPayload($paymentMean): array
    {
        return [
            'payment_means_code' => $paymentMean->payment_means_code,
            'payee_financial_account_id' => $paymentMean->payee_financial_account_id,
            'payee_financial_account_name' => $paymentMean->payee_financial_account_name,
            'financial_institution_branch_id' => $paymentMean->financial_institution_branch_id,
        ];
    }

    private function nrsSubmissionPayload($submission): array
    {
        return [
            'correlation_id' => $submission->correlation_id,
            'action' => $submission->action,
            'status' => $submission->status,
            'http_status_code' => $submission->http_status_code,
            'error_code' => $submission->error_code,
            'error_message' => $submission->error_message,
            'attempt_number' => $submission->attempt_number,
            'response_time_ms' => $submission->response_time_ms,
            'submitted_at' => $submission->submitted_at,
            'responded_at' => $submission->responded_at,
        ];
    }

    private function stateTransitionPayload($transition): array
    {
        return [
            'from_status' => $this->enumValue($transition->from_status),
            'to_status' => $this->enumValue($transition->to_status),
            'trigger' => $transition->trigger,
            'note' => $transition->note,
            'created_at' => $transition->created_at,
        ];
    }
}
