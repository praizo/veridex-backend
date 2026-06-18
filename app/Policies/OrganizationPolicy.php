<?php

namespace App\Policies;

use App\Models\Organization;
use App\Models\User;
use App\Policies\Concerns\ChecksOrganizationRole;

class OrganizationPolicy
{
    use ChecksOrganizationRole;

    public function view(User $user, Organization $organization): bool
    {
        return $this->belongsToCurrentOrganization($user, $organization->id)
            && $this->hasAnyRole($user, ['owner', 'admin', 'editor', 'accountant', 'viewer'], $organization);
    }

    public function update(User $user, Organization $organization): bool
    {
        return $this->belongsToCurrentOrganization($user, $organization->id)
            && $this->hasAnyRole($user, ['owner', 'admin'], $organization);
    }

    public function viewReports(User $user, Organization $organization): bool
    {
        return $this->belongsToCurrentOrganization($user, $organization->id)
            && $this->hasAnyRole($user, ['owner', 'admin', 'accountant'], $organization);
    }

    public function viewActivityLogs(User $user, Organization $organization): bool
    {
        return $this->viewReports($user, $organization);
    }
}
