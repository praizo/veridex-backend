<?php

namespace App\Policies\Concerns;

use App\Models\Organization;
use App\Models\User;

trait ChecksOrganizationRole
{
    protected function belongsToCurrentOrganization(User $user, int $organizationId): bool
    {
        return (int) $user->currentOrganizationId() === $organizationId;
    }

    protected function roleFor(User $user, ?Organization $organization = null): ?string
    {
        $organizationId = $organization?->id ?? $user->currentOrganizationId();

        if (! $organizationId) {
            return null;
        }

        $organization = $user->organizations()
            ->where('organizations.id', $organizationId)
            ->first();

        return $organization?->pivot?->role;
    }

    protected function hasAnyRole(User $user, array $roles, ?Organization $organization = null): bool
    {
        return in_array($this->roleFor($user, $organization), $roles, true);
    }
}
