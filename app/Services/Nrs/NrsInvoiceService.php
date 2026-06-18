<?php

namespace App\Services\Nrs;

use App\Enums\InvoiceStatus;
use App\Enums\NrsAction;
use App\Enums\NrsSubmissionStatus;
use App\Exceptions\InvoiceStateException;
use App\Exceptions\NrsApiException;
use App\Models\Invoice;
use App\Models\NrsSubmission;
use App\Models\User;
use App\Services\ActivityLog\ActivityLogService;
use App\Services\Invoice\InvoiceStateService;
use App\Support\SensitiveDataRedactor;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;

class NrsInvoiceService
{
    public function __construct(
        protected NrsClient $client,
        protected NrsPayloadBuilder $payloadBuilder,
        protected ActivityLogService $activityLog,
        protected InvoiceStateService $stateService,
        protected SensitiveDataRedactor $redactor
    ) {}

    /**
     * Self Health Check — Confirms APP setup and readiness for transmission.
     * Should be called before attempting to transmit invoices.
     */
    public function selfHealthCheck(): array
    {
        try {
            $response = $this->client->get('api/v1/invoice/transmit/self-health-check');
            $responseData = $response->json();

            Log::info('NRS Self Health Check Result', $responseData);

            return $responseData;
        } catch (\Throwable $e) {
            Log::error('NRS Self Health Check Failed: '.$e->getMessage());
            throw $e;
        }
    }

    /**
     * Lookup IRN — Retrieves details about the invoice and the involved parties.
     */
    public function lookupIrn(string $irn): array
    {
        try {
            $response = $this->client->get("api/v1/invoice/transmit/lookup/{$irn}");
            $responseData = $response->json();

            Log::info('NRS Lookup IRN Result', ['irn' => $irn, 'response' => $responseData]);

            return $responseData;
        } catch (\Throwable $e) {
            Log::error('NRS Lookup IRN Failed: '.$e->getMessage());
            throw $e;
        }
    }

    /**
     * Step 1: Validate the invoice data on NRS.
     */
    public function validate(Invoice $invoice, ?User $actor = null): array
    {
        return $this->validateInvoice($invoice, $actor);
    }

    public function validateInvoice(Invoice $invoice, ?User $actor = null): array
    {
        $this->stateService->transition($invoice, InvoiceStatus::PENDING_VALIDATION, $actor, 'NRS Validation Started');
        try {
            $result = $this->performAction($invoice, NrsAction::VALIDATE, 'api/v1/invoice/validate');
            $this->stateService->transition($invoice, InvoiceStatus::VALIDATED, $actor, 'NRS Validation Succeeded');
            $this->activityLog->log($actor, 'NRS_VALIDATE', $invoice, 'Successfully validated invoice on NRS.');

            return $result;
        } catch (\Throwable $e) {
            $this->stateService->transition($invoice, InvoiceStatus::VALIDATION_FAILED, $actor, 'NRS Validation Failed', null, ['error' => $e->getMessage()]);
            $this->activityLog->log($actor, 'NRS_VALIDATE_FAIL', $invoice, 'Failed to validate invoice on NRS.', ['raw_error' => $e->getMessage()]);
            throw $e;
        }
    }

    /**
     * Step 2: Sign the invoice on NRS (Generates IRN).
     */
    public function sign(Invoice $invoice, ?User $actor = null): array
    {
        return $this->signInvoice($invoice, $actor);
    }

    public function signInvoice(Invoice $invoice, ?User $actor = null): array
    {
        $this->stateService->transition($invoice, InvoiceStatus::PENDING_SIGNING, $actor, 'NRS Signing Started');
        try {
            $signResult = $this->performAction($invoice, NrsAction::SIGN, 'api/v1/invoice/sign');
            $this->stateService->transition($invoice, InvoiceStatus::SIGNED, $actor, 'NRS Signing Succeeded');
            $this->activityLog->log($actor, 'NRS_SIGN', $invoice, "Successfully signed invoice. IRN: {$invoice->irn}");
        } catch (\Throwable $e) {
            $this->stateService->transition($invoice, InvoiceStatus::SIGN_FAILED, $actor, 'NRS Signing Failed', null, ['error' => $e->getMessage()]);
            $this->activityLog->log($actor, 'NRS_SIGN_FAIL', $invoice, 'Failed to sign invoice on NRS.', ['raw_error' => $e->getMessage()]);
            throw $e;
        }

        // Attempt transmission but don't let its failure mask the successful sign
        try {
            $transmitResult = $this->transmitInvoice($invoice->fresh(), $actor);
        } catch (\Throwable $e) {
            Log::warning("Sign succeeded but transmit failed for invoice {$invoice->id}: {$e->getMessage()}");

            return [
                'sign' => $signResult,
                'transmit' => null,
                'sign_succeeded' => true,
                'transmit_failed' => true,
                'transmit_error' => $e->getMessage(),
            ];
        }

        return [
            'sign' => $signResult,
            'transmit' => $transmitResult,
        ];
    }

    /**
     * Step 3: Transmit the signed invoice to the customer.
     */
    public function transmit(Invoice $invoice, ?User $actor = null): array
    {
        return $this->transmitInvoice($invoice, $actor);
    }

    public function transmitInvoice(Invoice $invoice, ?User $actor = null): array
    {
        if (! $invoice->irn) {
            throw new NrsApiException('Cannot transmit an invoice without an IRN.');
        }

        $this->stateService->transition($invoice, InvoiceStatus::PENDING_TRANSMIT, $actor, 'NRS Transmit Started');
        try {
            $result = $this->performAction($invoice, NrsAction::TRANSMIT, "api/v1/invoice/transmit/{$invoice->irn}");
            $this->stateService->transition($invoice, InvoiceStatus::TRANSMITTED, $actor, 'NRS Transmit Succeeded');
            $this->activityLog->log($actor, 'NRS_TRANSMIT', $invoice, 'Successfully transmitted invoice on NRS.');

            return $result;
        } catch (\Throwable $e) {
            $this->stateService->transition($invoice, InvoiceStatus::TRANSMIT_FAILED, $actor, 'NRS Transmit Failed', null, ['error' => $e->getMessage()]);
            $this->activityLog->log($actor, 'NRS_TRANSMIT_FAIL', $invoice, 'Failed to transmit invoice on NRS.', ['raw_error' => $e->getMessage()]);
            throw $e;
        }
    }

    /**
     * Update payment status of a signed invoice on NRS.
     */
    public function updatePayment(Invoice $invoice, string $status, ?string $reference = null, ?User $actor = null): array
    {
        return $this->updatePaymentStatus($invoice, $status, $reference, $actor);
    }

    public function updatePaymentStatus(Invoice $invoice, string $status, ?string $reference = null, ?User $actor = null): array
    {
        if (! $invoice->irn) {
            throw new NrsApiException('Cannot update payment status for an invoice without an IRN.');
        }

        try {
            $payload = [
                'payment_status' => strtoupper($status),
                'reference' => $reference,
            ];

            $result = $this->performAction(
                $invoice,
                NrsAction::UPDATE_PAYMENT,
                "api/v1/invoice/update/{$invoice->irn}",
                $payload
            );

            // Log event/activity
            $this->activityLog->log(
                $actor,
                'NRS_PAYMENT_UPDATE',
                $invoice,
                "Updated payment status on NRS to {$status}."
            );

            return $result;
        } catch (\Throwable $e) {
            if (str_contains(strtolower($e->getMessage()), 'terminal state')) {
                Log::warning("NRS Payment Update: Invoice {$invoice->irn} is in a terminal state on NRS. Failing status update.", [
                    'invoice_id' => $invoice->id,
                    'status' => $status,
                ]);

                $this->activityLog->log(
                    $actor,
                    'NRS_PAYMENT_UPDATE_SKIP',
                    $invoice,
                    'NRS payment status update skipped: Invoice is in a terminal state on NRS.'
                );

                throw new NrsApiException('Invoice is in a terminal state on NRS and cannot be updated.', 400, $e);
            }

            Log::error('NRS Payment Update Failed: '.$e->getMessage());
            $this->activityLog->log(
                $actor,
                'NRS_PAYMENT_UPDATE_FAIL',
                $invoice,
                'Failed to update payment status on NRS.',
                ['raw_error' => $e->getMessage()]
            );
            throw $e;
        }
    }

    public function downloadOfficialArtifacts(Invoice $invoice): array
    {
        return app(NrsArtifactService::class)->downloadAndStorePdf($invoice);
    }

    /**
     * Step 4: Confirm receipt of a transmitted invoice.
     * Per NRS docs: PATCH /api/v1/invoice/transmit/{IRN}
     */
    public function confirm(Invoice $invoice): array
    {
        throw new InvoiceStateException('Confirmation is deferred for this lifecycle. A transmitted invoice is terminal.');
    }

    /**
     * Helper to perform NRS actions with submission tracking.
     */
    protected function performAction(Invoice $invoice, NrsAction $action, string $endpoint, ?array $customPayload = null): array
    {
        $organization = $invoice->organization;

        // Safety Check: Ensure organization profile is complete
        if (! $organization->tin || ! $organization->nrs_business_id) {
            throw new NrsApiException(
                'Incomplete Organization Profile: Your Company TIN and NRS Business ID are required for FIRS submission. '.
                'Please update your organization settings first.'
            );
        }

        $idempotencyKey = (string) Str::uuid7();
        $payload = $customPayload ?? (in_array($action, [NrsAction::TRANSMIT, NrsAction::CONFIRM]) ? [] : $this->payloadBuilder->buildFullInvoicePayload($invoice));

        // Record the attempt
        $submission = NrsSubmission::create([
            'invoice_id' => $invoice->id,
            'action' => $action,
            'status' => NrsSubmissionStatus::PENDING,
            'idempotency_key' => $idempotencyKey,
            'request_payload' => $this->redactor->redact($payload),
            'submitted_at' => now(),
        ]);

        $headers = ['X-Idempotency-Key' => $idempotencyKey];
        $debugContext = [
            'organization_id' => $invoice->organization_id,
            'submission_id' => $submission->id,
            'invoice_id' => $invoice->id,
            'invoice_uuid' => $invoice->uuid,
            'irn' => $invoice->irn,
            'action' => $action->value,
            'endpoint' => $endpoint,
            'idempotency_key' => $idempotencyKey,
        ];

        try {
            $response = match ($action) {
                NrsAction::TRANSMIT => $this->client->post($endpoint, [], $headers, $debugContext),
                NrsAction::CONFIRM => $this->client->patch($endpoint, [], $headers, $debugContext),
                NrsAction::UPDATE_PAYMENT => $this->client->patch($endpoint, $payload, $headers, $debugContext),
                default => $this->client->post($endpoint, $payload, $headers, $debugContext),
            };

            $responseData = $response->json();

            // Update submission success
            $submission->update([
                'status' => NrsSubmissionStatus::SUCCESS,
                'http_status_code' => $response->status(),
                'response_payload' => $this->redactor->redact($responseData),
                'responded_at' => now(),
            ]);

            // If signing, update the IRN from the response
            if ($action === NrsAction::SIGN && isset($responseData['irn'])) {
                $invoice->forceFill(['irn' => $responseData['irn']])->save();
            }

            return $responseData;

        } catch (\Throwable $e) {
            // Update submission failure
            $submission->update([
                'status' => NrsSubmissionStatus::FAILED,
                'http_status_code' => $e->getCode() ?: 500,
                'response_payload' => $this->redactor->redact(['error' => $e->getMessage()]),
                'error_message' => 'NRS request failed. See redacted response payload for details.',
                'responded_at' => now(),
            ]);

            throw $e;
        }
    }
}
