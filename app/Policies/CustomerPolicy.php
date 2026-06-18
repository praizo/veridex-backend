<?php

namespace App\Policies;

use App\Models\Customer;
use App\Models\User;
use App\Policies\Concerns\ChecksOrganizationRole;

class CustomerPolicy
{
    use ChecksOrganizationRole;

    public function viewAny(User $user): bool
    {
        return $this->hasAnyRole($user, ['owner', 'admin', 'editor', 'accountant', 'viewer']);
    }

    public function view(User $user, Customer $customer): bool
    {
        return $this->belongsToCurrentOrganization($user, $customer->organization_id)
            && $this->viewAny($user);
    }

    public function create(User $user): bool
    {
        return $this->hasAnyRole($user, ['owner', 'admin', 'editor']);
    }

    public function update(User $user, Customer $customer): bool
    {
        return $this->belongsToCurrentOrganization($user, $customer->organization_id)
            && $this->hasAnyRole($user, ['owner', 'admin', 'editor']);
    }

    public function delete(User $user, Customer $customer): bool
    {
        return $this->update($user, $customer);
    }

    public function export(User $user): bool
    {
        return $this->hasAnyRole($user, ['owner', 'admin', 'accountant']);
    }
}
