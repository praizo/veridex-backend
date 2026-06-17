<?php

namespace App\Traits;

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

        return array_merge($user->toArray(), [
            'current_organization_role' => $role,
            'platform_role' => $user->platform_role,
        ]);
    }
}
