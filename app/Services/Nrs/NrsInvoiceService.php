<?php

namespace App\Services\Nrs;

use App\Enums\InvoiceStatus;
use App\Enums\NrsAction;
use App\Enums\NrsSubmissionStatus;
use App\Exceptions\NrsApiException;
use App\Models\Invoice;
use App\Models\NrsSubmission;
use App\Services\ActivityLogService;
use App\Services\InvoiceStateService;
use Illuminate\Support\Str;

class NrsInvoiceService
{
    public function __construct(
        protected NrsClient $client,
        protected NrsPayloadBuilder $payloadBuilder,
        protected ActivityLogService $activityLog,
        protected InvoiceStateService $stateService
    ) {}

    /**
     * Step 1: Validate the invoice data on NRS.
     */
    public function validate(Invoice $invoice): array
    {
        $this->stateService->transition($invoice, InvoiceStatus::PENDING_VALIDATION, request()->user(), 'NRS Validation Started');
        try {
            $result = $this->performAction($invoice, NrsAction::VALIDATE, 'api/v1/invoice/validate');
            $this->stateService->transition($invoice, InvoiceStatus::VALIDATED, request()->user(), 'NRS Validation Succeeded');
            $this->activityLog->log(request()->user(), 'NRS_VALIDATE', $invoice, 'Successfully validated invoice on NRS.');

            return $result;
        } catch (\Throwable $e) {
            $this->stateService->transition($invoice, InvoiceStatus::VALIDATION_FAILED, request()->user(), 'NRS Validation Failed', null, ['error' => $e->getMessage()]);
            $this->activityLog->log(request()->user(), 'NRS_VALIDATE_FAIL', $invoice, 'Failed to validate invoice on NRS: '.$e->getMessage());
            throw $e;
        }
    }

    /**
     * Step 2: Sign the invoice on NRS (Generates IRN).
     */
    public function sign(Invoice $invoice): array
    {
        $this->stateService->transition($invoice, InvoiceStatus::PENDING_SIGNING, request()->user(), 'NRS Signing Started');
        try {
            $result = $this->performAction($invoice, NrsAction::SIGN, 'api/v1/invoice/sign');
            $this->stateService->transition($invoice, InvoiceStatus::SIGNED, request()->user(), 'NRS Signing Succeeded');
            $this->activityLog->log(request()->user(), 'NRS_SIGN', $invoice, "Successfully signed invoice. IRN: {$invoice->irn}");

            // Auto-transmit after successful signing
            $this->transmit($invoice);

            return $result;
        } catch (\Throwable $e) {
            $this->stateService->transition($invoice, InvoiceStatus::SIGN_FAILED, request()->user(), 'NRS Signing Failed', null, ['error' => $e->getMessage()]);
            $this->activityLog->log(request()->user(), 'NRS_SIGN_FAIL', $invoice, 'Failed to sign invoice on NRS: '.$e->getMessage());
            throw $e;
        }
    }

    /**
     * Step 3: Transmit the signed invoice to the customer.
     */
    public function transmit(Invoice $invoice): array
    {
        if (! $invoice->irn) {
            throw new NrsApiException('Cannot transmit an invoice without an IRN.');
        }

        $this->stateService->transition($invoice, InvoiceStatus::PENDING_TRANSMIT, request()->user(), 'NRS Transmit Started');
        try {
            $result = $this->performAction($invoice, NrsAction::TRANSMIT, "api/v1/invoice/transmit/{$invoice->irn}");
            $this->stateService->transition($invoice, InvoiceStatus::TRANSMITTED, request()->user(), 'NRS Transmit Succeeded');
            $this->activityLog->log(request()->user(), 'NRS_TRANSMIT', $invoice, 'Successfully transmitted invoice on NRS.');

            return $result;
        } catch (\Throwable $e) {
            $this->stateService->transition($invoice, InvoiceStatus::TRANSMIT_FAILED, request()->user(), 'NRS Transmit Failed', null, ['error' => $e->getMessage()]);
            $this->activityLog->log(request()->user(), 'NRS_TRANSMIT_FAIL', $invoice, 'Failed to transmit invoice on NRS: '.$e->getMessage());
            throw $e;
        }
    }

    /**
     * Step 4: Confirm the invoice receipt.
     */
    public function confirm(Invoice $invoice): array
    {
        if (! $invoice->irn) {
            throw new NrsApiException('Cannot confirm an invoice without an IRN.');
        }

        try {
            $result = $this->performAction($invoice, NrsAction::CONFIRM, "api/v1/invoice/confirm/{$invoice->irn}");
            $this->stateService->transition($invoice, InvoiceStatus::CONFIRMED, request()->user(), 'NRS Confirm Succeeded');
            $this->activityLog->log(request()->user(), 'NRS_CONFIRM', $invoice, 'Successfully confirmed invoice on NRS.');

            return $result;
        } catch (\Exception $e) {
            $this->activityLog->log(request()->user(), 'NRS_CONFIRM_FAIL', $invoice, 'Failed to confirm invoice on NRS: '.$e->getMessage());
            throw $e;
        }
    }

    /**
     * Helper to perform NRS actions with submission tracking.
     */
    protected function performAction(Invoice $invoice, NrsAction $action, string $endpoint): array
    {
        $organization = $invoice->organization;

        // Safety Check: Ensure organization profile is complete
        if (! $organization->tin || ! $organization->nrs_business_id) {
            throw new NrsApiException(
                'Incomplete Organization Profile: Your Company TIN and NRS Business ID are required for FIRS submission. '.
                'Please update your organization settings first.'
            );
        }

        $idempotencyKey = (string) Str::uuid();
        $payload = in_array($action, [NrsAction::TRANSMIT, NrsAction::CONFIRM]) ? [] : $this->payloadBuilder->buildFullInvoicePayload($invoice);

        // Record the attempt
        $submission = NrsSubmission::create([
            'invoice_id' => $invoice->id,
            'action' => $action,
            'status' => NrsSubmissionStatus::PENDING,
            'idempotency_key' => $idempotencyKey,
            'request_payload' => $payload,
            'submitted_at' => now(),
        ]);

        $headers = ['X-Idempotency-Key' => $idempotencyKey];

        try {
            $response = match ($action) {
                NrsAction::TRANSMIT => $this->client->post($endpoint, [], $headers),
                NrsAction::CONFIRM => $this->client->get($endpoint, [], $headers),
                default => $this->client->post($endpoint, $payload, $headers),
            };

            $responseData = $response->json();

            // Update submission success
            $submission->update([
                'status' => NrsSubmissionStatus::SUCCESS,
                'http_status_code' => $response->status(),
                'response_payload' => $responseData,
                'responded_at' => now(),
            ]);

            // If signing, update the IRN from the response
            if ($action === NrsAction::SIGN && isset($responseData['irn'])) {
                $invoice->update(['irn' => $responseData['irn']]);
            }

            return $responseData;

        } catch (\Throwable $e) {
            // Update submission failure
            $submission->update([
                'status' => NrsSubmissionStatus::FAILED,
                'http_status_code' => $e->getCode() ?: 500,
                'response_payload' => ['error' => $e->getMessage()],
                'error_message' => $e->getMessage(),
                'responded_at' => now(),
            ]);

            throw $e;
        }
    }
}
