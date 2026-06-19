<?php

namespace App\Jobs;

use App\Models\ActivityLog;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;

class WriteActivityLogJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public function __construct(
        private readonly ?int $userId,
        private readonly ?int $organizationId,
        private readonly string $action,
        private readonly string $subjectType,
        private readonly int $subjectId,
        private readonly string $description,
        private readonly ?array $metadata,
        private readonly ?string $ipAddress,
        private readonly ?string $userAgent,
    ) {}

    public function handle(): void
    {
        ActivityLog::create([
            'organization_id' => $this->organizationId,
            'user_id' => $this->userId,
            'action' => $this->action,
            'subject_type' => $this->subjectType,
            'subject_id' => $this->subjectId,
            'description' => $this->description,
            'metadata' => $this->metadata,
            'ip_address' => $this->ipAddress,
            'user_agent' => $this->userAgent,
        ]);
    }
}
