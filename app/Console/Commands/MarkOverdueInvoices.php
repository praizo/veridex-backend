<?php

namespace App\Console\Commands;

use App\Models\Invoice;
use App\Services\Operations\OperationalMetricService;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Log;

class MarkOverdueInvoices extends Command
{
    protected $signature = 'invoices:mark-overdue';

    protected $description = 'Detect overdue unpaid invoices for operational visibility until lifecycle states are expanded.';

    public function handle(OperationalMetricService $metrics): int
    {
        $count = Invoice::whereDate('due_date', '<', now()->toDateString())
            ->whereIn('payment_status', ['PENDING', 'PARTIAL'])
            ->count();

        if ($count > 0) {
            $metrics->increment('overdue_invoice_count', 1, ['count' => $count]);
        }

        Log::info('Overdue invoice scan completed', ['count' => $count]);
        $this->info("Detected {$count} overdue unpaid invoices.");

        return self::SUCCESS;
    }
}
