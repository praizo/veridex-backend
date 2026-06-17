<?php

namespace App\Services\Platform;

use App\Models\ActivityLog;
use App\Models\NrsApiLog;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

class PlatformSystemService
{
    public function failedJobsCount(?string $payloadLike = null): int
    {
        if (! Schema::hasTable('failed_jobs')) {
            return 0;
        }

        return DB::table('failed_jobs')
            ->when($payloadLike, fn ($query) => $query->where('payload', 'like', $payloadLike))
            ->count();
    }

    public function overview(): array
    {
        return [
            'failed_jobs' => $this->failedJobsCount(),
            'recent_failed_jobs' => Schema::hasTable('failed_jobs')
                ? DB::table('failed_jobs')->latest('failed_at')->limit(10)->get(['uuid', 'connection', 'queue', 'exception', 'failed_at'])
                : [],
            'nrs_failures' => NrsApiLog::where('status_code', '>=', 400)->latest()->limit(10)->get(),
            'mail_failures' => $this->failedJobsCount('%Mail%'),
            'activity_last_24h' => ActivityLog::where('created_at', '>=', now()->subDay())->count(),
        ];
    }
}
