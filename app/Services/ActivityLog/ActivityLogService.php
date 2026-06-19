<?php

namespace App\Services\ActivityLog;

use App\Jobs\WriteActivityLogJob;
use App\Models\ActivityLog;
use App\Models\Organization;
use App\Models\User;
use App\Support\SensitiveDataRedactor;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Request;

class ActivityLogService
{
    public function __construct(
        private readonly SensitiveDataRedactor $redactor,
    ) {}

    public function log(?User $user, string $action, Model $subject, string $description, ?array $metadata = null): ActivityLog
    {
        $payload = $this->payload($user, $action, $subject, $description, $metadata);

        return ActivityLog::create($payload);
    }

    public function logQueued(?User $user, string $action, Model $subject, string $description, ?array $metadata = null): void
    {
        $payload = $this->payload($user, $action, $subject, $description, $metadata);

        WriteActivityLogJob::dispatch(
            userId: $payload['user_id'],
            organizationId: $payload['organization_id'],
            action: $payload['action'],
            subjectType: $payload['subject_type'],
            subjectId: $payload['subject_id'],
            description: $payload['description'],
            metadata: $payload['metadata'],
            ipAddress: $payload['ip_address'],
            userAgent: $payload['user_agent'],
        )->afterCommit();
    }

    private function payload(?User $user, string $action, Model $subject, string $description, ?array $metadata): array
    {
        $organizationId = $user?->current_organization_id
            ?? (property_exists($subject, 'organization_id') || isset($subject->organization_id) ? $subject->organization_id : null)
            ?? ($subject instanceof Organization ? $subject->id : null);

        return [
            'organization_id' => $organizationId,
            'user_id' => $user?->id,
            'action' => $action,
            'subject_type' => get_class($subject),
            'subject_id' => $subject->id,
            'description' => $description,
            'metadata' => $metadata ? $this->redactor->redact($metadata) : null,
            'ip_address' => Request::ip(),
            'user_agent' => Request::userAgent(),
        ];
    }
}
