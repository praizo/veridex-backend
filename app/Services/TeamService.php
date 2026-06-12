<?php

namespace App\Services;

use App\Events\TeamMemberAdded;
use App\Models\Organization;
use App\Models\User;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Password;
use Illuminate\Support\Str;

class TeamService
{
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
        $newUser = User::where('email', $email)->first();
        $wasCreated = false;

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

        return ['user' => $newUser, 'was_created' => $wasCreated];
    }

    private function buildFrontendUrl(string $baseUrl, string $path): string
    {
        return rtrim($baseUrl, '/').'/'.ltrim($path, '/');
    }
}
