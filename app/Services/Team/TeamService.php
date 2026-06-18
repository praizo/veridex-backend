<?php

namespace App\Services\Team;

use App\Events\TeamMemberAdded;
use App\Events\TeamMemberRemoved;
use App\Events\TeamMemberRoleChanged;
use App\Models\Organization;
use App\Models\User;
use App\Services\ActivityLog\ActivityLogService;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Password;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

class TeamService
{
    public function __construct(
        private readonly ActivityLogService $activityLog,
    ) {}

    public function listMembers(Organization $org): Collection
    {
        return $org->users()->withPivot('role')->get();
    }

    public function ensureManager(User $actor, Organization $org): string
    {
        $role = $this->roleFor($actor, $org);

        if (! in_array($role, ['owner', 'admin'], true)) {
            abort(403, 'Unauthorized');
        }

        return (string) $role;
    }

    public function ensureTargetMember(User $member, Organization $org): string
    {
        $target = $org->users()->where('user_id', $member->id)->first();

        if (! $target) {
            abort(404, 'Member not found in this organization');
        }

        return (string) $target->pivot->role;
    }

    /**
     * Add a member to an organization — handles user creation, attachment, and invitation dispatch.
     *
     * @return array{user: User, was_created: bool}
     */
    public function addMember(
        Organization $org,
        string $email,
        string $role,
        ?string $firstName,
        ?string $lastName,
        User $inviter,
        string $frontendBaseUrl
    ): array {
        $actorRole = $this->ensureManager($inviter, $org);

        if ($role === 'owner' && $actorRole !== 'owner') {
            abort(403, 'Only the owner can assign the owner role');
        }

        $newUser = User::where('email', $email)->first();
        $wasCreated = false;

        if ($newUser && $org->users()->where('user_id', $newUser->id)->exists()) {
            throw ValidationException::withMessages([
                'email' => ['User is already a member of this organization'],
            ]);
        }

        if ($newUser?->isSuperAdmin()) {
            throw ValidationException::withMessages([
                'email' => ['Platform super admins cannot be added to an organization.'],
            ]);
        }

        if (! $newUser) {
            $newUser = User::create([
                'first_name' => $firstName,
                'last_name' => $lastName,
                'email' => $email,
                'password' => Hash::make(Str::password(32)),
                'current_organization_id' => $org->id,
                'onboarding_completed_at' => now(),
            ]);
            $wasCreated = true;
        } elseif (! $newUser->email_verified_at && ($firstName || $lastName)) {
            $newUser->forceFill(['first_name' => $firstName, 'last_name' => $lastName])->save();
        }

        $org->users()->attach($newUser->id, ['role' => $role]);

        $updates = [];
        if (! $newUser->current_organization_id) {
            $updates['current_organization_id'] = $org->id;
        }
        if (! $newUser->onboarding_completed_at) {
            $updates['onboarding_completed_at'] = now();
        }
        if ($updates !== []) {
            $newUser->forceFill($updates)->save();
        }

        $requiresPasswordSetup = ! $newUser->email_verified_at;
        $actionUrl = $requiresPasswordSetup
            ? $this->buildFrontendUrl($frontendBaseUrl, '/reset-password?token='.Password::broker()->createToken($newUser).'&email='.urlencode($newUser->email))
            : $this->buildFrontendUrl($frontendBaseUrl, '/login');

        TeamMemberAdded::dispatch(
            user: $newUser,
            organizationName: $org->name,
            inviterName: $inviter->name,
            role: $role,
            actionUrl: $actionUrl,
            requiresPasswordSetup: $requiresPasswordSetup,
        );

        $this->activityLog->log(
            $inviter,
            'team.member.added',
            $org,
            "{$newUser->email} was added to {$org->name}.",
            [
                'member_id' => $newUser->uuid,
                'member_email' => $newUser->email,
                'role' => $role,
                'was_created' => $wasCreated,
            ],
        );

        return ['user' => $newUser, 'was_created' => $wasCreated];
    }

    public function updateRole(Organization $org, User $member, string $role, User $actor): void
    {
        $actorRole = $this->ensureManager($actor, $org);
        $targetRole = $this->ensureTargetMember($member, $org);

        if ($targetRole === 'owner' && $actorRole !== 'owner') {
            abort(403, 'Admins cannot modify the organization owner');
        }

        if ($role === 'owner' && $actorRole !== 'owner') {
            throw ValidationException::withMessages([
                'role' => ['Only the owner can assign the owner role'],
            ]);
        }

        $member->organizations()->updateExistingPivot($org->id, ['role' => $role]);

        if ($targetRole !== $role) {
            TeamMemberRoleChanged::dispatch(
                member: $member,
                organizationName: $org->name,
                actorName: $actor->name,
                oldRole: $targetRole,
                newRole: $role,
            );

            $this->activityLog->log(
                $actor,
                'team.member.role_changed',
                $org,
                "{$member->email} role changed from {$targetRole} to {$role}.",
                [
                    'member_id' => $member->uuid,
                    'member_email' => $member->email,
                    'old_role' => $targetRole,
                    'new_role' => $role,
                ],
            );
        }
    }

    public function removeMember(Organization $org, User $member, User $actor): void
    {
        if ($member->id === $actor->id) {
            abort(422, 'You cannot remove yourself from the organization');
        }

        $actorRole = $this->ensureManager($actor, $org);
        $targetRole = $this->ensureTargetMember($member, $org);

        if ($targetRole === 'owner' && $actorRole !== 'owner') {
            abort(403, 'Admins cannot remove the organization owner');
        }

        $member->organizations()->detach($org->id);

        TeamMemberRemoved::dispatch(
            member: $member,
            organizationName: $org->name,
            actorName: $actor->name,
            removedRole: $targetRole,
        );

        $this->activityLog->log(
            $actor,
            'team.member.removed',
            $org,
            "{$member->email} was removed from {$org->name}.",
            [
                'member_id' => $member->uuid,
                'member_email' => $member->email,
                'removed_role' => $targetRole,
            ],
        );
    }

    private function roleFor(User $user, Organization $org): ?string
    {
        return $user->organizations()
            ->where('organization_id', $org->id)
            ->first()
            ?->pivot
            ?->role;
    }

    private function buildFrontendUrl(string $baseUrl, string $path): string
    {
        return rtrim($baseUrl, '/').'/'.ltrim($path, '/');
    }
}
