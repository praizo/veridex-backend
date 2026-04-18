<?php

namespace App\Services;

use App\Models\Invoice;
use App\Models\InvoiceStateTransition;
use App\Models\User;
use App\Mail\InvoiceConfirmedMail;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Log;
use App\Exceptions\InvoiceStateException;
use App\Enums\InvoiceStatus;

class InvoiceStateService
{
    public function transition(
        Invoice $invoice,
        InvoiceStatus $toStatus,
        ?User $user,
        string $trigger,
        ?string $note = null,
        ?array $metadata = null
    ): InvoiceStateTransition {
        $fromStatus = $invoice->status;

        // Allow same-state transitions (idempotency)
        if ($fromStatus === $toStatus) {
            return InvoiceStateTransition::where('invoice_id', $invoice->id)
                ->where('to_status', $toStatus->value)
                ->latest()
                ->first() ?? new InvoiceStateTransition();
        }

        $this->validateTransition($fromStatus, $toStatus);

        $transition = InvoiceStateTransition::create([
            'invoice_id' => $invoice->id,
            'user_id'    => $user?->id,
            'from_status'=> $fromStatus->value,
            'to_status'  => $toStatus->value,
            'trigger'    => $trigger,
            'note'       => $note,
            'metadata'   => $metadata,
            'ip_address' => request()->ip(),
        ]);

        $invoice->update(['status' => $toStatus]);

        $newStatus = $toStatus->value;
        Log::info("Invoice state transition: [{$invoice->irn}] moved to [{$newStatus}] by [{$trigger}]");

        // Trigger notifications if confirmed
        if ($newStatus === 'confirmed' && $invoice->customer?->email) {
            try {
                Mail::to($invoice->customer->email)->send(new InvoiceConfirmedMail($invoice));
                Log::info("Invoice confirmation email dispatched to: {$invoice->customer->email}");
            } catch (\Exception $e) {
                Log::error("Failed to send invoice confirmation email: " . $e->getMessage());
            }
        }

        return $transition;
    }

    private function validateTransition(InvoiceStatus $from, InvoiceStatus $to): void
    {
        // Allow idempotency
        if ($from === $to) {
            return;
        }

        $allowed = [
            InvoiceStatus::DRAFT->value => [InvoiceStatus::PENDING_VALIDATION, InvoiceStatus::CANCELLED],
            InvoiceStatus::PENDING_VALIDATION->value => [InvoiceStatus::VALIDATED, InvoiceStatus::VALIDATION_FAILED],
            InvoiceStatus::VALIDATED->value => [InvoiceStatus::PENDING_SIGNING],
            InvoiceStatus::VALIDATION_FAILED->value => [InvoiceStatus::DRAFT, InvoiceStatus::PENDING_VALIDATION],
            InvoiceStatus::PENDING_SIGNING->value => [InvoiceStatus::SIGNED, InvoiceStatus::SIGN_FAILED],
            InvoiceStatus::SIGNED->value => [InvoiceStatus::PENDING_TRANSMIT],
            InvoiceStatus::SIGN_FAILED->value => [InvoiceStatus::VALIDATED, InvoiceStatus::PENDING_SIGNING],
            InvoiceStatus::PENDING_TRANSMIT->value => [InvoiceStatus::TRANSMITTED, InvoiceStatus::TRANSMIT_FAILED],
            InvoiceStatus::TRANSMITTED->value => [InvoiceStatus::CONFIRMED],
            InvoiceStatus::TRANSMIT_FAILED->value => [InvoiceStatus::SIGNED, InvoiceStatus::PENDING_TRANSMIT],
        ];

        $allowedTo = $allowed[$from->value] ?? [];

        if (!in_array($to, $allowedTo)) {
            throw new InvoiceStateException("Cannot transition from '{$from->value}' to '{$to->value}'");
        }
    }
}
