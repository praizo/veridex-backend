<?php

namespace Tests\Feature;

use App\Models\Customer;
use App\Models\Invoice;
use App\Models\Organization;
use App\Models\User;
use App\Notifications\ResetPasswordNotification;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Str;
use Tests\TestCase;

class PlatformAdminTest extends TestCase
{
    use RefreshDatabase;

    public function test_platform_endpoints_require_super_admin(): void
    {
        $organization = $this->organization('Tenant Org');
        $tenantUser = User::factory()->create(['current_organization_id' => $organization->id]);
        $tenantUser->organizations()->attach($organization->id, ['role' => 'owner']);

        $this->getJson('/api/v1/platform/summary')->assertUnauthorized();

        $this->actingAs($tenantUser)
            ->getJson('/api/v1/platform/summary')
            ->assertForbidden();
    }

    public function test_super_admin_can_view_cross_tenant_summary(): void
    {
        $admin = $this->platformAdmin();
        $first = $this->organization('First Org');
        $second = $this->organization('Second Org');
        $this->invoice($first, 'INV-001', 1000);
        $this->invoice($second, 'INV-002', 2000, 'transmitted');

        $this->actingAs($admin)
            ->getJson('/api/v1/platform/summary')
            ->assertOk()
            ->assertJsonPath('data.organizations.total', 2)
            ->assertJsonPath('data.invoices.total', 2)
            ->assertJsonPath('data.invoices.transmitted', 1);
    }

    public function test_invalid_sort_fields_are_normalized(): void
    {
        $admin = $this->platformAdmin();
        $this->organization('Sort Org');

        $this->actingAs($admin)
            ->getJson('/api/v1/platform/organizations?sort=name_that_does_not_exist')
            ->assertOk()
            ->assertJsonStructure(['data', 'meta']);
    }

    public function test_platform_mutation_writes_activity_log(): void
    {
        $admin = $this->platformAdmin();
        $organization = $this->organization('Audit Org');

        $this->actingAs($admin)
            ->patchJson("/api/v1/platform/organizations/{$organization->uuid}", [
                'platform_status' => 'suspended',
                'reason' => 'Compliance review',
            ])
            ->assertOk()
            ->assertJsonPath('data.platform_status', 'suspended');

        $this->assertDatabaseHas('activity_logs', [
            'organization_id' => $organization->id,
            'user_id' => $admin->id,
            'action' => 'platform.organization.updated',
        ]);
    }

    public function test_platform_admin_is_not_required_to_belong_to_an_organization(): void
    {
        $admin = $this->platformAdmin([
            'current_organization_id' => null,
            'onboarding_completed_at' => null,
        ]);

        $this->actingAs($admin)
            ->getJson('/api/v1/platform/summary')
            ->assertOk();

        $this->actingAs($admin)
            ->getJson('/api/v1/dashboard')
            ->assertForbidden()
            ->assertJsonPath('message', 'Onboarding not completed. Please set up your business first.');
    }

    public function test_suspended_users_cannot_login(): void
    {
        $user = User::factory()->create([
            'email' => 'suspended@example.com',
            'password' => 'password',
            'suspended_at' => now(),
        ]);

        $this->postJson('/api/v1/login', [
            'email' => $user->email,
            'password' => 'password',
        ])->assertUnprocessable()
            ->assertJsonValidationErrors('email');
    }

    public function test_platform_invite_command_creates_platform_only_super_admin(): void
    {
        Notification::fake();

        $exitCode = Artisan::call('platform:invite-super-admin', [
            'email' => 'ops@example.com',
            '--first-name' => 'Ops',
            '--last-name' => 'Lead',
        ]);

        $this->assertSame(0, $exitCode);

        $user = User::where('email', 'ops@example.com')->firstOrFail();

        $this->assertSame('Ops', $user->first_name);
        $this->assertSame('Lead', $user->last_name);
        $this->assertNull($user->current_organization_id);
        $this->assertNull($user->onboarding_completed_at);
        $this->assertTrue($user->isSuperAdmin());

        $this->assertDatabaseHas('platform_admins', [
            'user_id' => $user->id,
            'role' => 'super_admin',
            'status' => 'active',
        ]);

        Notification::assertSentTo($user, ResetPasswordNotification::class);
    }

    public function test_platform_revoke_command_unsets_super_admin_without_deleting_user(): void
    {
        $admin = $this->platformAdmin(['email' => 'ops@example.com']);

        $exitCode = Artisan::call('platform:revoke-super-admin', [
            'email' => $admin->email,
        ]);

        $this->assertSame(0, $exitCode);
        $this->assertNotNull($admin->fresh());
        $this->assertFalse($admin->fresh('platformAdmin')->isSuperAdmin());

        $this->assertDatabaseHas('platform_admins', [
            'user_id' => $admin->id,
            'role' => 'super_admin',
            'status' => 'revoked',
        ]);
    }

    public function test_platform_admin_can_revoke_own_access_when_another_active_super_admin_exists(): void
    {
        $admin = $this->platformAdmin();
        $this->platformAdmin(['email' => 'backup@example.com']);

        $this->actingAs($admin)
            ->patchJson("/api/v1/platform/users/{$admin->uuid}", [
                'platform_role' => null,
                'reason' => 'Handing over platform operations',
            ])
            ->assertOk()
            ->assertJsonPath('data.platform_role', null);

        $this->assertDatabaseHas('platform_admins', [
            'user_id' => $admin->id,
            'role' => 'super_admin',
            'status' => 'revoked',
        ]);
    }

    public function test_last_active_platform_admin_cannot_suspend_or_revoke_self(): void
    {
        $admin = $this->platformAdmin();

        $this->actingAs($admin)
            ->patchJson("/api/v1/platform/users/{$admin->uuid}", [
                'platform_role' => null,
                'reason' => 'Unsafe self revoke',
            ])
            ->assertUnprocessable()
            ->assertJsonValidationErrors('platform_role');

        $this->actingAs($admin)
            ->patchJson("/api/v1/platform/users/{$admin->uuid}", [
                'suspended' => true,
                'reason' => 'Unsafe self suspend',
            ])
            ->assertUnprocessable()
            ->assertJsonValidationErrors('suspended');
    }

    private function organization(string $name): Organization
    {
        return Organization::create([
            'name' => $name,
            'slug' => Str::slug($name).'-'.fake()->unique()->numberBetween(1000, 9999),
            'tin' => fake()->numerify('########-####'),
            'email' => fake()->safeEmail(),
            'nrs_business_id' => fake()->uuid(),
            'onboarding_status' => 'onboarded',
            'verified_at' => now(),
        ]);
    }

    private function platformAdmin(array $attributes = []): User
    {
        $user = User::factory()->create($attributes);
        $user->platformAdmin()->create([
            'role' => 'super_admin',
            'status' => 'active',
        ]);

        return $user->fresh('platformAdmin');
    }

    private function invoice(Organization $organization, string $number, float $amount, string $status = 'signed'): Invoice
    {
        $customer = Customer::create([
            'organization_id' => $organization->id,
            'first_name' => 'Customer',
            'last_name' => $number,
            'type' => 'business',
            'tin' => fake()->numerify('########-####'),
            'email' => fake()->safeEmail(),
        ]);

        return Invoice::create([
            'organization_id' => $organization->id,
            'customer_id' => $customer->id,
            'invoice_number' => $number,
            'status' => $status,
            'payment_status' => 'PENDING',
            'issue_date' => today(),
            'due_date' => today()->addDays(30),
            'document_currency_code' => 'NGN',
            'line_extension_amount' => $amount,
            'tax_exclusive_amount' => $amount,
            'tax_inclusive_amount' => $amount,
            'payable_amount' => $amount,
        ]);
    }
}
