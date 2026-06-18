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

        return $this->paginated(
            $query->paginate($filters->perPage),
            fn (Organization $organization) => $this->payload($organization)
        );
    }

    public function find(string $value): Organization
    {
        return Organization::where('uuid', $value)->orWhere('id', $value)->firstOrFail();
    }

    public function show(string $value): array
    {
        return $this->payload($this->find($value)->loadCount(['users', 'invoices']), includeAdminNotes: true);
    }

    public function update(User $actor, Organization $organization, UpdatePlatformOrganizationDTO $dto): array
    {
        $auditedFields = [
            'name',
            'tin',
            'email',
            'telephone',
            'street_name',
            'city_name',
            'postal_zone',
            'country_code',
            'business_description',
            'service_id',
            'nrs_business_id',
            'platform_status',
            'onboarding_status',
            'verified_at',
            'suspended_at',
            'admin_notes',
        ];

        $before = $this->auditSnapshot($organization->only($auditedFields));

        if ($dto->businessFields !== []) {
            $organization->fill($dto->businessFields);
        }

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
            after: $this->auditSnapshot($organization->only($auditedFields)),
            reason: $dto->reason,
        );

        return $this->payload($organization->fresh()->loadCount(['users', 'invoices']), includeAdminNotes: true);
    }

    private function payload(Organization $organization, bool $includeAdminNotes = false): array
    {
        $payload = [
            'id' => $organization->uuid,
            'uuid' => $organization->uuid,
            'name' => $organization->name,
            'slug' => $organization->slug,
            'tin' => $organization->tin,
            'email' => $organization->email,
            'telephone' => $organization->telephone,
            'street_name' => $organization->street_name,
            'city_name' => $organization->city_name,
            'postal_zone' => $organization->postal_zone,
            'country_code' => $organization->country_code,
            'business_description' => $organization->business_description,
            'service_id' => $organization->service_id,
            'nrs_business_id' => $organization->nrs_business_id,
            'platform_status' => $organization->platform_status,
            'onboarding_status' => $organization->onboarding_status,
            'verified_at' => $organization->verified_at,
            'suspended_at' => $organization->suspended_at,
            'users_count' => $organization->users_count ?? null,
            'invoices_count' => $organization->invoices_count ?? null,
            'created_at' => $organization->created_at,
            'updated_at' => $organization->updated_at,
        ];

        if ($includeAdminNotes) {
            $payload['admin_notes'] = $organization->admin_notes;
        }

        return $payload;
    }

    private function auditSnapshot(array $values): array
    {
        return collect($values)
            ->map(fn ($value) => $value instanceof \DateTimeInterface ? $value->format(DATE_ATOM) : $value)
            ->all();
    }
}
