<?php

namespace App\Console\Commands;

use App\Services\OperationalMetricService;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class MonitorQueueHealth extends Command
{
    protected $signature = 'ops:queue-health';

    protected $description = 'Report queue health for email, webhook, PDF, and NRS operational visibility.';

    public function handle(OperationalMetricService $metrics): int
    {
        $pendingJobs = DB::table('jobs')->count();
        $failedJobs = DB::table('failed_jobs')->count();

        Log::info('Queue health snapshot', [
            'pending_jobs' => $pendingJobs,
            'failed_jobs' => $failedJobs,
            'monitored_workloads' => [
                'otp_emails',
                'invoice_confirmation_emails',
                'webhook_jobs',
                'pdf_generation',
                'nrs_polling',
            ],
        ]);

        if ($failedJobs > 0) {
            $metrics->increment('queue_failure_count', 1, [
                'failed_jobs' => $failedJobs,
                'pending_jobs' => $pendingJobs,
            ]);
        }

        $this->info("Queue health: {$pendingJobs} pending, {$failedJobs} failed.");

        return self::SUCCESS;
    }
}
