<?php

namespace App\Console\Commands;

use App\Models\Invoice;
use App\Services\InvoiceStateService;
use Carbon\Carbon;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Log;

class RecoverStuckInvoices extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'invoices:recover-stuck {--minutes=30 : Minutes to consider an invoice stuck}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Identifies and fails invoices stuck in transient states for too long.';

    protected InvoiceStateService $stateService;

    public function __construct(InvoiceStateService $stateService)
    {
        parent::__construct();
        $this->stateService = $stateService;
    }

    /**
     * Execute the console command.
     */
    public function handle()
    {
        $minutes = $this->option('minutes');
        $threshold = Carbon::now()->subMinutes($minutes);

        // Transient states that shouldn't persist for long
        $transientStates = [
            'validating',
            'signing',
            'transmitting',
        ];

        $stuckInvoices = Invoice::whereIn('status', $transientStates)
            ->where('updated_at', '<=', $threshold)
            ->get();

        if ($stuckInvoices->isEmpty()) {
            $this->info('No stuck invoices found.');

            return 0;
        }

        $this->info("Found {$stuckInvoices->count()} stuck invoices. Resetting to failed...");

        foreach ($stuckInvoices as $invoice) {
            $this->info("Processing IRN: {$invoice->irn} (Current Status: {$invoice->status->value})");

            try {
                // Determine which failure state to move to based on last attempted action
                $targetStatus = match ($invoice->status->value) {
                    'validating' => 'validation_failed',
                    'signing' => 'sign_failed',
                    'transmitting' => 'transmit_failed',
                    default => 'failed',
                };

                $this->stateService->transition(
                    $invoice,
                    $targetStatus,
                    null,
                    'system_recovery',
                    "Automatically failed by system recovery after being stuck for more than {$minutes} minutes."
                );

                Log::info("Stuck Invoice Recovered: [{$invoice->irn}] moved from [{$invoice->status->value}] to [{$targetStatus}]");
            } catch (\Exception $e) {
                $this->error("Failed to recover invoice {$invoice->irn}: ".$e->getMessage());
            }
        }

        $this->info('Recovery process complete.');

        return 0;
    }
}
