<?php

namespace App\Services\Organization;

use App\Models\Organization;
use App\Models\User;
use Illuminate\Auth\Access\AuthorizationException;

class OrganizationService
{
    public function switch(User $user, int $organizationId): User
    {
        if ($user->isSuperAdmin()) {
            throw new AuthorizationException('Platform super admins cannot switch into an organization.');
        }

        if (! $user->organizations()->where('organization_id', $organizationId)->exists()) {
            throw new AuthorizationException('Unauthorized access to organization');
        }

        $user->update(['current_organization_id' => $organizationId]);

        return $user->fresh('currentOrganization');
    }

    public function current(User $user): Organization
    {
        return Organization::findOrFail($user->currentOrganizationId());
    }

    public function updateCurrent(User $user, array $data): Organization
    {
        $org = $this->current($user);
        $org->update($data);

        return $org;
    }
}
