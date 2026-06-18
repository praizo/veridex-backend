<?php

namespace App\Services\Platform;

use App\DTOs\Platform\PlatformListFiltersDTO;
use App\DTOs\Platform\UpdatePlatformUserDTO;
use App\Events\PlatformUserUpdated;
use App\Models\Organization;
use App\Models\PlatformAdmin;
use App\Models\User;
use App\Services\Platform\Concerns\PaginatesPlatformResults;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Validation\ValidationException;

class PlatformUserService
{
    use PaginatesPlatformResults;

    public function list(PlatformListFiltersDTO $filters): array
    {
        $query = User::query()->with(['currentOrganization', 'platformAdmin'])->withCount('organizations');

        $query
            ->when($filters->search, function (Builder $query, string $search) {
                $query->where(function (Builder $nested) use ($search) {
                    $nested->where('first_name', 'like', "%{$search}%")
                        ->orWhere('last_name', 'like', "%{$search}%")
                        ->orWhere('email', 'like', "%{$search}%");
                });
            })
            ->when($filters->status, function (Builder $query, string $status) {
                match ($status) {
                    'active' => $query->whereNull('suspended_at')->whereNotNull('email_verified_at'),
                    'suspended' => $query->whereNotNull('suspended_at'),
                    'unverified' => $query->whereNull('email_verified_at'),
                    default => null,
                };
            });

        if ($filters->organizationId !== null) {
            $organizationId = $this->resolveOrganizationId($filters->organizationId);

            $organizationId
                ? $query->whereHas('organizations', fn (Builder $organizationQuery) => $organizationQuery->whereKey($organizationId))
                : $query->whereRaw('1 = 0');
        }

        if ($filters->sort === 'platform_role') {
            $query
                ->leftJoin('platform_admins', 'platform_admins.user_id', '=', 'users.id')
                ->select('users.*')
                ->orderBy('platform_admins.role', $filters->direction);
        } else {
            $this->applySort($query, $filters, [
                'created_at' => 'users.created_at',
                'email' => 'email',
                'first_name' => 'first_name',
            ], 'users.created_at');
        }

        return $this->paginated(
            $query->paginate($filters->perPage),
            fn (User $user) => $this->payload($user)
        );
    }

    public function show(User $user): array
    {
        return $this->payload($user->load('currentOrganization', 'organizations', 'platformAdmin'), includeOrganizations: true);
    }

    public function update(User $actor, User $user, UpdatePlatformUserDTO $dto): array
    {
        if ($actor->is($user)) {
            if ($dto->hasPlatformRole && $dto->platformRole !== 'super_admin') {
                $this->ensureAnotherActiveSuperAdmin($actor, 'platform_role');
            }

            if ($dto->hasSuspended && $dto->suspended) {
                $this->ensureAnotherActiveSuperAdmin($actor, 'suspended');
            }
        }

        $user->loadMissing('platformAdmin');
        $before = $this->userAuditSnapshot($user);

        if ($dto->hasFirstName) {
            $user->first_name = $dto->firstName;
        }

        if ($dto->hasLastName) {
            $user->last_name = $dto->lastName;
        }

        if ($dto->hasPlatformRole) {
            $this->updatePlatformRole($actor, $user, $dto->platformRole);
        }

        if ($dto->hasSuspended) {
            $user->suspended_at = $dto->suspended ? now() : null;
        }

        if ($dto->hasEmailVerified) {
            $user->email_verified_at = $dto->emailVerified ? ($user->email_verified_at ?? now()) : null;
        }

        $user->save();

        PlatformUserUpdated::dispatch(
            actor: $actor,
            user: $user->fresh('platformAdmin'),
            before: $before,
            after: $this->userAuditSnapshot($user->fresh('platformAdmin')),
            reason: $dto->reason,
        );

        return $this->payload($user->fresh(['currentOrganization', 'platformAdmin']));
    }

    private function payload(User $user, bool $includeOrganizations = false): array
    {
        $payload = [
            'id' => $user->uuid,
            'uuid' => $user->uuid,
            'first_name' => $user->first_name,
            'last_name' => $user->last_name,
            'name' => $user->name,
            'email' => $user->email,
            'email_verified_at' => $user->email_verified_at,
            'suspended_at' => $user->suspended_at,
            'current_organization' => $this->organizationPayload($user->currentOrganization),
            'organizations_count' => $user->organizations_count ?? null,
            'platform_role' => $user->platform_role,
            'created_at' => $user->created_at,
            'updated_at' => $user->updated_at,
        ];

        if ($includeOrganizations) {
            $payload['organizations'] = $user->organizations
                ->map(fn ($organization) => array_merge(
                    $this->organizationPayload($organization) ?? [],
                    ['role' => $organization->pivot?->role],
                ))
                ->values()
                ->all();
        }

        return $payload;
    }

    private function organizationPayload($organization): ?array
    {
        if (! $organization) {
            return null;
        }

        return [
            'id' => $organization->uuid,
            'uuid' => $organization->uuid,
            'name' => $organization->name,
            'tin' => $organization->tin,
            'email' => $organization->email,
        ];
    }

    private function resolveOrganizationId(string $value): ?int
    {
        return Organization::query()
            ->where('uuid', $value)
            ->orWhere('id', $value)
            ->value('id');
    }

    private function updatePlatformRole(User $actor, User $user, ?string $role): void
    {
        if ($role === null) {
            $user->platformAdmin()->update(['status' => 'revoked']);

            return;
        }

        if ($user->current_organization_id || $user->organizations()->exists()) {
            throw ValidationException::withMessages([
                'platform_role' => ['Organization users cannot be granted platform super admin access.'],
            ]);
        }

        PlatformAdmin::updateOrCreate(
            ['user_id' => $user->id],
            [
                'role' => $role,
                'status' => 'active',
                'invited_by' => $actor->id,
            ],
        );
    }

    private function ensureAnotherActiveSuperAdmin(User $actor, string $field): void
    {
        $exists = PlatformAdmin::query()
            ->where('role', 'super_admin')
            ->where('status', 'active')
            ->whereHas('user', fn (Builder $query) => $query->whereNull('suspended_at'))
            ->where('user_id', '!=', $actor->id)
            ->exists();

        if (! $exists) {
            throw ValidationException::withMessages([
                $field => ['At least one other active platform super admin is required before changing your own access.'],
            ]);
        }
    }

    private function userAuditSnapshot(User $user): array
    {
        return $this->auditSnapshot([
            'platform_role' => $user->platform_role,
            'first_name' => $user->first_name,
            'last_name' => $user->last_name,
            'suspended_at' => $user->suspended_at,
            'email_verified_at' => $user->email_verified_at,
        ]);
    }

    private function auditSnapshot(array $values): array
    {
        return collect($values)
            ->map(fn ($value) => $value instanceof \DateTimeInterface ? $value->format(DATE_ATOM) : $value)
            ->all();
    }
}
