<?php

namespace Tests\Feature;

use App\Models\Organization;
use App\Models\OtpCode;
use App\Models\User;
use App\Notifications\TeamInvitationNotification;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Str;
use Tests\TestCase;

class TeamOnboardingProfileTest extends TestCase
{
    use RefreshDatabase;

    private function createOrganization(string $name): Organization
    {
        return Organization::create([
            'name' => $name,
            'slug' => str($name)->slug()->toString(),
            'tin' => '12345678-0001',
            'email' => str($name)->slug().'@test.com',
            'nrs_business_id' => (string) Str::uuid(),
        ]);
    }

    private function createMember(Organization $organization, string $role): User
    {
        $user = User::factory()->create([
            'current_organization_id' => $organization->id,
            'onboarding_completed_at' => now(),
        ]);

        $user->organizations()->attach($organization->id, ['role' => $role]);

        return $user;
    }

    public function test_admin_can_invite_unregistered_user_into_current_organization(): void
    {
        Notification::fake();

        $organization = $this->createOrganization('Invite Org');
        $admin = $this->createMember($organization, 'admin');

        $response = $this->actingAs($admin)->postJson('/api/v1/team/members', [
            'first_name' => 'New',
            'last_name' => 'Teammate',
            'email' => 'new.teammate@example.com',
            'role' => 'editor',
        ]);

        $response->assertCreated()
            ->assertJsonPath('data.email', 'new.teammate@example.com')
            ->assertJsonPath('data.status', 'invited');

        $invited = User::where('email', 'new.teammate@example.com')->firstOrFail();

        $this->assertNull($invited->email_verified_at);
        $this->assertSame($organization->id, $invited->current_organization_id);
        $this->assertNotNull($invited->onboarding_completed_at);
        $this->assertDatabaseHas('organization_user', [
            'organization_id' => $organization->id,
            'user_id' => $invited->id,
            'role' => 'editor',
        ]);

        Notification::assertSentTo($invited, TeamInvitationNotification::class);
    }

    public function test_admin_can_add_existing_user_and_invitation_email_is_sent(): void
    {
        Notification::fake();

        $organization = $this->createOrganization('Existing Invite Org');
        $admin = $this->createMember($organization, 'admin');
        $existing = User::factory()->create([
            'first_name' => 'Existing',
            'last_name' => 'Member',
            'email' => 'existing.member@example.com',
        ]);

        $response = $this->actingAs($admin)->postJson('/api/v1/team/members', [
            'first_name' => 'Name Should Not',
            'last_name' => 'Replace Existing Verified User',
            'email' => 'existing.member@example.com',
            'role' => 'viewer',
        ]);

        $response->assertOk()
            ->assertJsonPath('data.name', 'Existing Member')
            ->assertJsonPath('data.status', 'active');

        $this->assertDatabaseHas('organization_user', [
            'organization_id' => $organization->id,
            'user_id' => $existing->id,
            'role' => 'viewer',
        ]);

        Notification::assertSentTo($existing, TeamInvitationNotification::class);
    }

    public function test_existing_pending_user_receives_password_setup_invitation_not_login(): void
    {
        Notification::fake();

        $organization = $this->createOrganization('Pending Invite Org');
        $admin = $this->createMember($organization, 'admin');
        $pending = User::factory()->unverified()->create([
            'first_name' => 'Pending',
            'last_name' => 'Existing',
            'email' => 'pending.existing@example.com',
        ]);

        $this->actingAs($admin)->postJson('/api/v1/team/members', [
            'first_name' => 'Pending Existing',
            'last_name' => 'Full Name',
            'email' => 'pending.existing@example.com',
            'role' => 'editor',
        ])->assertOk()
            ->assertJsonPath('data.name', 'Pending Existing Full Name')
            ->assertJsonPath('data.status', 'invited');

        Notification::assertSentTo(
            $pending->fresh(),
            TeamInvitationNotification::class,
            function (TeamInvitationNotification $notification, array $channels, User $notifiable): bool {
                $mail = $notification->toMail($notifiable);

                return str_contains((string) $mail->actionUrl, '/reset-password?token=')
                    && ! str_contains((string) $mail->actionUrl, '/login')
                    && $mail->view === 'emails.team-invitation';
            }
        );
    }

    public function test_invited_user_signup_keeps_existing_organization_membership(): void
    {
        Notification::fake();

        $organization = $this->createOrganization('Signup Invite Org');
        $admin = $this->createMember($organization, 'admin');

        $this->actingAs($admin)->postJson('/api/v1/team/members', [
            'first_name' => 'Pending',
            'last_name' => 'Member',
            'email' => 'pending.member@example.com',
            'role' => 'viewer',
        ])->assertCreated();

        $this->postJson('/api/v1/register', [
            'first_name' => 'Pending Member',
            'last_name' => 'Updated',
            'email' => 'pending.member@example.com',
            'password' => 'Password1!',
            'password_confirmation' => 'Password1!',
        ])->assertOk()
            ->assertJsonPath('requires_otp', true);

        $otp = OtpCode::where('email', 'pending.member@example.com')
            ->where('type', 'registration')
            ->firstOrFail();

        $verify = $this->postJson('/api/v1/verify-otp', [
            'email' => 'pending.member@example.com',
            'code' => $otp->code,
            'type' => 'registration',
        ]);

        $verify->assertCreated()
            ->assertJsonPath('user.current_organization_id', $organization->id)
            ->assertJsonPath('user.current_organization_role', 'viewer');

        $user = User::where('email', 'pending.member@example.com')->firstOrFail();
        $this->assertSame('Pending Member Updated', $user->name);
        $this->assertNotNull($user->email_verified_at);
        $this->assertNotNull($user->onboarding_completed_at);
    }

    public function test_user_can_update_own_profile_name(): void
    {
        $organization = $this->createOrganization('Profile Org');
        $user = $this->createMember($organization, 'viewer');

        $response = $this->actingAs($user)->patchJson('/api/v1/profile', [
            'first_name' => 'Updated',
            'last_name' => 'Name',
        ]);

        $response->assertOk()
            ->assertJsonPath('user.name', 'Updated Name')
            ->assertJsonPath('user.current_organization_role', 'viewer');

        $this->assertSame('Updated Name', $user->fresh()->name);
    }

    public function test_user_can_change_password_from_profile_with_current_password(): void
    {
        $organization = $this->createOrganization('Password Org');
        $user = $this->createMember($organization, 'viewer');

        $response = $this->actingAs($user)->patchJson('/api/v1/profile', [
            'first_name' => $user->first_name,
            'last_name' => $user->last_name,
            'current_password' => 'password',
            'password' => 'NewPassword1!',
            'password_confirmation' => 'NewPassword1!',
        ]);

        $response->assertOk()
            ->assertJsonPath('message', 'Profile and password updated successfully.');

        $this->assertTrue(Hash::check('NewPassword1!', $user->fresh()->password));
    }

    public function test_profile_password_change_requires_current_password(): void
    {
        $organization = $this->createOrganization('Password Guard Org');
        $user = $this->createMember($organization, 'viewer');

        $this->actingAs($user)->patchJson('/api/v1/profile', [
            'first_name' => $user->first_name,
            'last_name' => $user->last_name,
            'current_password' => 'wrong-password',
            'password' => 'NewPassword1!',
            'password_confirmation' => 'NewPassword1!',
        ])->assertStatus(422)
            ->assertJsonValidationErrors('current_password');

        $this->assertTrue(Hash::check('password', $user->fresh()->password));
    }
}
