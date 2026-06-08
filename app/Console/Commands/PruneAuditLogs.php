<?php

namespace App\Console\Commands;

use App\Models\ActivityLog;
use App\Models\NrsApiLog;
use App\Models\NrsSubmission;
use Illuminate\Console\Command;

class PruneAuditLogs extends Command
{
    protected $signature = 'audit:prune';

    protected $description = 'Prune technical and compliance audit logs according to configured retention windows.';

    public function handle(): int
    {
        $technicalCutoff = now()->subDays((int) config('audit.technical_log_retention_days', 30));
        $complianceCutoff = now()->subDays((int) config('audit.compliance_log_retention_days', 2555));

        $technicalDeleted = NrsApiLog::where('created_at', '<', $technicalCutoff)->delete();
        $activityDeleted = ActivityLog::where('created_at', '<', $complianceCutoff)->delete();
        $submissionDeleted = NrsSubmission::where('created_at', '<', $complianceCutoff)->delete();

        $this->info("Pruned {$technicalDeleted} technical NRS API logs.");
        $this->info("Pruned {$activityDeleted} activity logs.");
        $this->info("Pruned {$submissionDeleted} NRS submissions.");

        return self::SUCCESS;
    }
}
