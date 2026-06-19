<?php

namespace App\Policies;

use App\Models\Product;
use App\Models\User;
use App\Policies\Concerns\ChecksOrganizationRole;

class ProductPolicy
{
    use ChecksOrganizationRole;

    public function viewAny(User $user): bool
    {
        return $this->hasAnyRole($user, ['owner', 'admin', 'editor', 'viewer']);
    }

    public function view(User $user, Product $product): bool
    {
        return $this->belongsToCurrentOrganization($user, $product->organization_id)
            && $this->viewAny($user);
    }

    public function create(User $user): bool
    {
        return $this->hasAnyRole($user, ['owner', 'admin', 'editor']);
    }

    public function update(User $user, Product $product): bool
    {
        return $this->belongsToCurrentOrganization($user, $product->organization_id)
            && $this->hasAnyRole($user, ['owner', 'admin', 'editor']);
    }

    public function delete(User $user, Product $product): bool
    {
        return $this->update($user, $product);
    }
}
