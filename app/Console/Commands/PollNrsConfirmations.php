<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use App\Models\Invoice;
use App\Enums\InvoiceStatus;
use App\Services\Nrs\NrsInvoiceService;
use Illuminate\Support\Facades\Log;

class PollNrsConfirmations extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'nrs:poll-confirmations';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Poll NRS gateway to confirm invoices that have been successfully transmitted.';

    /**
     * Execute the console command.
     */
    public function handle(NrsInvoiceService $nrsInvoiceService)
    {
        $this->info('Starting NRS Confirmation Polling...');

        // Find invoices that were transmitted but not yet confirmed.
        $invoices = Invoice::where('status', InvoiceStatus::TRANSMITTED)->get();

        if ($invoices->isEmpty()) {
            $this->info('No transmitted invoices pending confirmation.');
            return Command::SUCCESS;
        }

        $successCount = 0;
        $failCount = 0;

        foreach ($invoices as $invoice) {
            try {
                $this->info("Attempting to confirm Invoice ID: {$invoice->id} (IRN: {$invoice->irn})");
                $nrsInvoiceService->confirm($invoice);
                $successCount++;
                $this->info("Successfully confirmed Invoice ID: {$invoice->id}");
            } catch (\Exception $e) {
                $failCount++;
                $this->error("Failed to confirm Invoice ID: {$invoice->id}. Error: " . $e->getMessage());
                Log::warning("Background Polling failed to confirm invoice.", [
                    'invoice_id' => $invoice->id,
                    'irn' => $invoice->irn,
                    'error' => $e->getMessage(),
                ]);
            }
        }

        $this->info("Polling complete. Success: {$successCount}, Failed: {$failCount}.");
        return Command::SUCCESS;
    }
}
