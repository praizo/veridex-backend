<?php

namespace App\Policies;

use App\Enums\InvoiceStatus;
use App\Models\Invoice;
use App\Models\User;
use App\Policies\Concerns\ChecksOrganizationRole;

class InvoicePolicy
{
    use ChecksOrganizationRole;

    public function viewAny(User $user): bool
    {
        return $this->hasAnyRole($user, ['owner', 'admin', 'editor', 'viewer']);
    }

    public function view(User $user, Invoice $invoice): bool
    {
        return $this->belongsToCurrentOrganization($user, $invoice->organization_id)
            && $this->viewAny($user);
    }

    public function create(User $user): bool
    {
        return $this->hasAnyRole($user, ['owner', 'admin', 'editor']);
    }

    public function update(User $user, Invoice $invoice): bool
    {
        return $this->belongsToCurrentOrganization($user, $invoice->organization_id)
            && $this->hasAnyRole($user, ['owner', 'admin', 'editor']);
    }

    public function delete(User $user, Invoice $invoice): bool
    {
        $status = $invoice->status instanceof InvoiceStatus
            ? $invoice->status
            : InvoiceStatus::tryFrom((string) $invoice->status);

        return $this->update($user, $invoice) && $status?->isEditable();
    }

    public function manageLifecycle(User $user, Invoice $invoice): bool
    {
        return $this->belongsToCurrentOrganization($user, $invoice->organization_id)
            && $this->hasAnyRole($user, ['owner', 'admin', 'editor']);
    }

    public function updatePayment(User $user, Invoice $invoice): bool
    {
        return $this->manageLifecycle($user, $invoice);
    }

    public function export(User $user): bool
    {
        return $this->hasAnyRole($user, ['owner', 'admin']);
    }
}
