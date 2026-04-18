<?php

namespace App\Services;

use App\Models\Invoice;
use App\Models\ActivityLog;
use App\Models\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Request;

class ActivityLogService
{
    public function log(?User $user, string $action, Model $subject, string $description, ?array $metadata = null): ActivityLog
    {
        $organizationId = $user ? $user->current_organization_id : (
            method_exists($subject, 'organization') ? $subject->organization_id : null
        );

        return ActivityLog::create([
            'organization_id' => $organizationId,
            'user_id' => $user?->id,
            'action' => $action,
            'subject_type' => get_class($subject),
            'subject_id' => $subject->id,
            'description' => $description,
            'metadata' => $metadata,
            'ip_address' => Request::ip(),
            'user_agent' => Request::userAgent(),
        ]);
    }
}
