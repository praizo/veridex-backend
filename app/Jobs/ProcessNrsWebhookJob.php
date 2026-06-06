<?php

namespace App\Jobs;

use App\Enums\InvoiceStatus;
use App\Models\Invoice;
use App\Models\NrsSubmission;
use App\Services\InvoiceStateService;
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

    public function handle(InvoiceStateService $stateService): void
    {
        $event = $this->payload['event'] ?? null;
        $irn = $this->payload['irn'] ?? null;
        $data = $this->payload['data'] ?? [];
        $timestamp = $this->payload['timestamp'] ?? now()->toIso8601String();

        if (! $event || ! $irn) {
            Log::warning('NRS Webhook Job: Missing event or IRN in payload', $this->payload);

            return;
        }

        // --- Idempotency Check ---
        $idempotencyKey = "webhook_{$event}_{$irn}_{$timestamp}";

        $exists = NrsSubmission::where('idempotency_key', $idempotencyKey)->exists();
        if ($exists) {
            Log::info("NRS Webhook Job: Duplicate event skipped [{$idempotencyKey}]");

            return;
        }

        // --- Find the Invoice ---
        $invoice = Invoice::where('irn', $irn)->first();

        if (! $invoice) {
            Log::warning("NRS Webhook Job: Invoice not found for IRN: {$irn}");

            return;
        }

        // --- Map APP Event to InvoiceStatus ---
        $statusMap = [
            'invoice.acknowledged' => InvoiceStatus::TRANSMITTED,
            'invoice.transmitted' => InvoiceStatus::CONFIRMED,
            'invoice.failed' => InvoiceStatus::TRANSMIT_FAILED,
            // Aliases — the APP may use different naming conventions
            'invoice.confirmed' => InvoiceStatus::CONFIRMED,
            'invoice.rejected' => InvoiceStatus::TRANSMIT_FAILED,
        ];

        $targetStatus = $statusMap[$event] ?? null;

        if (! $targetStatus) {
            Log::warning("NRS Webhook Job: Unknown event type [{$event}] for IRN: {$irn}");

            // Still record it so we have a trail
            NrsSubmission::create([
                'invoice_id' => $invoice->id,
                'user_id' => null,
                'idempotency_key' => $idempotencyKey,
                'action' => 'webhook_unknown',
                'status' => 'failed',
                'request_payload' => $this->payload,
                'error_message' => "Unknown webhook event: {$event}",
                'submitted_at' => now(),
                'responded_at' => now(),
            ]);

            return;
        }

        // --- Transition the Invoice State ---
        try {
            $note = $data['message'] ?? "Status update via NRS webhook: {$event}";

            $stateService->transition(
                invoice: $invoice,
                toStatus: $targetStatus,
                user: null,
                trigger: 'nrs_webhook',
                note: $note,
                metadata: $this->payload,
            );

            // Record successful webhook processing
            NrsSubmission::create([
                'invoice_id' => $invoice->id,
                'user_id' => null,
                'idempotency_key' => $idempotencyKey,
                'action' => 'webhook',
                'status' => 'success',
                'request_payload' => $this->payload,
                'response_payload' => ['transitioned_to' => $targetStatus->value],
                'submitted_at' => now(),
                'responded_at' => now(),
            ]);

            Log::info("NRS Webhook Job: Invoice [{$irn}] transitioned to [{$targetStatus->value}] via event [{$event}]");
        } catch (\Exception $e) {
            Log::error("NRS Webhook Job: Failed to process event [{$event}] for IRN [{$irn}]: {$e->getMessage()}");

            NrsSubmission::create([
                'invoice_id' => $invoice->id,
                'user_id' => null,
                'idempotency_key' => $idempotencyKey,
                'action' => 'webhook',
                'status' => 'failed',
                'request_payload' => $this->payload,
                'error_message' => $e->getMessage(),
                'submitted_at' => now(),
                'responded_at' => now(),
            ]);

            throw $e; // Re-throw so the queue can retry
        }
    }
}
