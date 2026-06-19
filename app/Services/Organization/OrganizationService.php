<?php

namespace App\Services\Organization;

use App\Models\Organization;
use App\Models\User;
use App\Services\ActivityLog\ActivityLogService;
use Illuminate\Auth\Access\AuthorizationException;

class OrganizationService
{
    public function __construct(
        private readonly ActivityLogService $activityLog,
    ) {}

    public function switch(User $user, int $organizationId): User
    {
        if ($user->isSuperAdmin()) {
            throw new AuthorizationException('Platform super admins cannot switch into an organization.');
        }

        if (! $user->organizations()->where('organization_id', $organizationId)->exists()) {
            throw new AuthorizationException('Unauthorized access to organization');
        }

        $previousOrganizationId = $user->current_organization_id;
        $user->update(['current_organization_id' => $organizationId]);
        $organization = Organization::findOrFail($organizationId);

        $this->activityLog->logQueued(
            $user,
            'organization.switched',
            $organization,
            "Switched active organization to {$organization->name}.",
            [
                'previous_organization_id' => $previousOrganizationId,
                'new_organization_id' => $organizationId,
            ],
        );

        return $user->fresh('currentOrganization');
    }

    public function current(User $user): Organization
    {
        return Organization::findOrFail($user->currentOrganizationId());
    }

    public function updateCurrent(User $user, array $data): Organization
    {
        $org = $this->current($user);
        $before = $org->only(array_keys($data));
        $org->update($data);

        $this->activityLog->logQueued(
            $user,
            'organization.updated',
            $org,
            "Organization settings updated for {$org->name}.",
            [
                'before' => $before,
                'after' => $org->fresh()->only(array_keys($data)),
            ],
        );

        return $org;
    }
}
