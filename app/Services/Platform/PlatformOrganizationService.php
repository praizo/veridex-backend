<?php

namespace App\Services\Platform;

use App\DTOs\Platform\PlatformListFiltersDTO;
use App\DTOs\Platform\UpdatePlatformOrganizationDTO;
use App\Events\PlatformOrganizationUpdated;
use App\Models\Organization;
use App\Models\User;
use App\Services\Platform\Concerns\PaginatesPlatformResults;
use Illuminate\Database\Eloquent\Builder;

class PlatformOrganizationService
{
    use PaginatesPlatformResults;

    public function list(PlatformListFiltersDTO $filters): array
    {
        $query = Organization::query()->withCount(['users', 'invoices']);

        $query
            ->when($filters->search, function (Builder $query, string $search) {
                $query->where(function (Builder $nested) use ($search) {
                    $nested->where('name', 'like', "%{$search}%")
                        ->orWhere('email', 'like', "%{$search}%")
                        ->orWhere('tin', 'like', "%{$search}%")
                        ->orWhere('nrs_business_id', 'like', "%{$search}%");
                });
            })
            ->when($filters->status, fn (Builder $query, string $status) => $query->where('platform_status', $status))
            ->when($filters->onboardingStatus, fn (Builder $query, string $status) => $query->where('onboarding_status', $status));

        $this->applySort($query, $filters, [
            'name' => 'name',
            'created_at' => 'created_at',
            'platform_status' => 'platform_status',
            'onboarding_status' => 'onboarding_status',
        ], 'created_at');

        return $this->paginated($query->paginate($filters->perPage));
    }

    public function find(string $value): Organization
    {
        return Organization::where('uuid', $value)->orWhere('id', $value)->firstOrFail();
    }

    public function show(string $value): Organization
    {
        return $this->find($value)->loadCount(['users', 'invoices']);
    }

    public function update(User $actor, Organization $organization, UpdatePlatformOrganizationDTO $dto): Organization
    {
        $before = $this->auditSnapshot($organization->only(['platform_status', 'onboarding_status', 'verified_at', 'suspended_at', 'admin_notes']));

        if ($dto->hasPlatformStatus) {
            $organization->platform_status = $dto->platformStatus;
            $organization->suspended_at = $dto->platformStatus === 'suspended' ? now() : null;
        }

        if ($dto->hasOnboardingStatus) {
            $organization->onboarding_status = $dto->onboardingStatus;
        }

        if ($dto->hasVerified) {
            $organization->verified_at = $dto->verified ? ($organization->verified_at ?? now()) : null;
        }

        if ($dto->hasAdminNotes) {
            $organization->admin_notes = $dto->adminNotes;
        }

        $organization->save();

        PlatformOrganizationUpdated::dispatch(
            actor: $actor,
            organization: $organization,
            before: $before,
            after: $this->auditSnapshot($organization->only(['platform_status', 'onboarding_status', 'verified_at', 'suspended_at', 'admin_notes'])),
            reason: $dto->reason,
        );

        return $organization->fresh();
    }

    private function auditSnapshot(array $values): array
    {
        return collect($values)
            ->map(fn ($value) => $value instanceof \DateTimeInterface ? $value->format(DATE_ATOM) : $value)
            ->all();
    }
}
