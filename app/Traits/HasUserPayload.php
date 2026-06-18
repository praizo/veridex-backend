<?php

namespace App\Traits;

use App\Models\Organization;
use App\Models\User;

trait HasUserPayload
{
    protected function userPayload(User $user): array
    {
        $user->load('currentOrganization');
        $organizationId = $user->currentOrganizationId();

        $role = $organizationId
            ? $user->organizations()
                ->where('organization_id', $organizationId)
                ->first()
                ?->pivot
                ?->role
            : null;

        return [
            'id' => $user->uuid,
            'first_name' => $user->first_name,
            'last_name' => $user->last_name,
            'name' => $user->name,
            'email' => $user->email,
            'email_verified_at' => $user->email_verified_at,
            'current_organization_id' => $user->current_organization_id,
            'current_organization' => $this->organizationPayload($user->currentOrganization),
            'current_organization_role' => $role,
            'onboarding_completed_at' => $user->onboarding_completed_at,
            'has_completed_onboarding' => $user->hasCompletedOnboarding(),
            'platform_role' => $user->platform_role,
        ];
    }

    protected function organizationPayload(?Organization $organization): ?array
    {
        if (! $organization) {
            return null;
        }

        return [
            'id' => $organization->uuid,
            'name' => $organization->name,
            'tin' => $organization->tin,
            'email' => $organization->email,
        ];
    }
}
