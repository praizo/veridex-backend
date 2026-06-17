<?php

namespace App\Services\ActivityLog;

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
        $organizationId = $user?->current_organization_id
            ?? (property_exists($subject, 'organization_id') || isset($subject->organization_id) ? $subject->organization_id : null)
            ?? ($subject instanceof Organization ? $subject->id : null);

        return ActivityLog::create([
            'organization_id' => $organizationId,
            'user_id' => $user?->id,
            'action' => $action,
            'subject_type' => get_class($subject),
            'subject_id' => $subject->id,
            'description' => $description,
            'metadata' => $metadata ? $this->redactor->redact($metadata) : null,
            'ip_address' => Request::ip(),
            'user_agent' => Request::userAgent(),
        ]);
    }
}
