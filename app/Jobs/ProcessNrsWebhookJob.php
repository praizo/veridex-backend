<?php

namespace App\Jobs;

use App\Enums\InvoiceStatus;
use App\Models\Invoice;
use App\Models\NrsSubmission;
use App\Services\InvoiceStateService;
use App\Services\OperationalMetricService;
use App\Support\SensitiveDataRedactor;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;

class ProcessNrsWebhookJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    /**
     * Number of times the job may be attempted.
     */
    public int $tries = 3;

    /**
     * The number of seconds to wait before retrying.
     */
    public array $backoff = [10, 30, 60];

    public function __construct(
        protected array $payload,
    ) {}

    public function handle(InvoiceStateService $stateService, SensitiveDataRedactor $redactor, OperationalMetricService $metrics): void
    {
        $redactedPayload = $redactor->redact($this->payload);
        $message = strtoupper(trim($this->payload['message'] ?? ''));
        $irn = $this->payload['irn'] ?? null;

        if (! $message || ! $irn) {
            Log::warning('NRS Webhook Job: Missing message or IRN in payload', $redactedPayload);

            return;
        }

        // --- Find the Invoice ---
        $invoice = Invoice::where('irn', $irn)->first();

        if (! $invoice) {
            Log::warning("NRS Webhook Job: Invoice not found for IRN: {$irn}");

            return;
        }

        // --- Atomic Idempotency Check ---
        // Use message + irn as the key; NRS sends stateless, event-driven notifications.
        $idempotencyKey = "webhook_{$message}_{$irn}";
        $submission = NrsSubmission::firstOrCreate([
            'idempotency_key' => $idempotencyKey,
        ], [
            'invoice_id' => $invoice->id,
            'user_id' => null,
            'action' => 'webhook',
            'status' => 'pending',
            'request_payload' => $redactedPayload,
            'submitted_at' => now(),
        ]);

        if (! $submission->wasRecentlyCreated) {
            $metrics->increment('webhook_duplicate_count');
            Log::info("NRS Webhook Job: Duplicate event skipped [{$idempotencyKey}]");

            return;
        }

        // --- Map NRS message status to InvoiceStatus ---
        // From NRS docs, the "message" field values are:
        //   TRANSMITTING  → Invoice is being sent to the receiver's APP
        //   ACKNOWLEDGED  → The receiver's APP has acknowledged receipt
        //   TRANSMITTED   → All parties have confirmed; transmission complete
        //   FAILED        → Transmission failed (APP unreachable, etc.)
        $statusMap = [
            'TRANSMITTING' => InvoiceStatus::PENDING_TRANSMIT,
            'ACKNOWLEDGED' => InvoiceStatus::TRANSMITTED,
            'TRANSMITTED' => InvoiceStatus::CONFIRMED,
            'FAILED' => InvoiceStatus::TRANSMIT_FAILED,
        ];

        $targetStatus = $statusMap[$message] ?? null;

        if (! $targetStatus) {
            Log::warning("NRS Webhook Job: Unknown message status [{$message}] for IRN: {$irn}");

            // Still record it so we have a trail
            $submission->update([
                'action' => 'webhook_unknown',
                'status' => 'failed',
                'error_message' => "Unknown webhook message status: {$message}",
                'responded_at' => now(),
            ]);

            return;
        }

        // --- Transition the Invoice State ---
        try {
            $note = "NRS transmission status: {$message}";

            $stateService->transition(
                invoice: $invoice,
                toStatus: $targetStatus,
                user: null,
                trigger: 'nrs_webhook',
                note: $note,
                metadata: $redactedPayload,
            );

            // Record successful webhook processing
            $submission->update([
                'status' => 'success',
                'response_payload' => ['transitioned_to' => $targetStatus->value],
                'responded_at' => now(),
            ]);

            Log::info("NRS Webhook Job: Invoice [{$irn}] transitioned to [{$targetStatus->value}] via message [{$message}]");
        } catch (\Exception $e) {
            Log::error("NRS Webhook Job: Failed to process message [{$message}] for IRN [{$irn}]: {$e->getMessage()}");

            $metrics->increment('invoice_state_transition_failures');

            $submission->update([
                'status' => 'failed',
                'error_message' => $e->getMessage(),
                'responded_at' => now(),
            ]);

            throw $e; // Re-throw so the queue can retry
        }
    }
}
