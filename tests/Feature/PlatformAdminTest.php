<?php

namespace Tests\Feature;

use App\Models\Customer;
use App\Models\ActivityLog;
use App\Models\Invoice;
use App\Models\NrsApiLog;
use App\Models\Organization;
use App\Models\User;
use App\Notifications\ResetPasswordNotification;
use App\Services\Team\TeamService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
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

    public function test_platform_api_rejects_granting_super_admin_to_organization_user(): void
    {
        $admin = $this->platformAdmin();
        $organization = $this->organization('Tenant Org');
        $tenantUser = User::factory()->create(['current_organization_id' => $organization->id]);
        $tenantUser->organizations()->attach($organization->id, ['role' => 'owner']);

        $this->actingAs($admin)
            ->patchJson("/api/v1/platform/users/{$tenantUser->uuid}", [
                'platform_role' => 'super_admin',
                'reason' => 'Incorrect elevation',
            ])
            ->assertUnprocessable()
            ->assertJsonValidationErrors('platform_role');

        $this->assertFalse($tenantUser->fresh('platformAdmin')->isSuperAdmin());
    }

    public function test_platform_commands_reject_granting_super_admin_to_organization_user(): void
    {
        Notification::fake();

        $organization = $this->organization('Tenant Org');
        $tenantUser = User::factory()->create([
            'email' => 'tenant@example.com',
            'current_organization_id' => $organization->id,
        ]);
        $tenantUser->organizations()->attach($organization->id, ['role' => 'owner']);

        $grantExitCode = Artisan::call('platform:super-admin', [
            'email' => $tenantUser->email,
        ]);
        $inviteExitCode = Artisan::call('platform:invite-super-admin', [
            'email' => $tenantUser->email,
        ]);

        $this->assertSame(1, $grantExitCode);
        $this->assertSame(1, $inviteExitCode);
        $this->assertFalse($tenantUser->fresh('platformAdmin')->isSuperAdmin());
        Notification::assertNothingSent();
    }

    public function test_super_admin_cannot_become_organization_user(): void
    {
        $organization = $this->organization('Tenant Org');
        $owner = User::factory()->create(['current_organization_id' => $organization->id]);
        $owner->organizations()->attach($organization->id, ['role' => 'owner']);
        $admin = $this->platformAdmin(['email' => 'ops@example.com']);

        $this->expectException(ValidationException::class);

        app(TeamService::class)->addMember(
            org: $organization,
            email: $admin->email,
            role: 'admin',
            firstName: null,
            lastName: null,
            inviter: $owner,
            frontendBaseUrl: 'https://dashboard.veridex.ng',
        );
    }

    public function test_super_admin_is_not_subject_to_business_onboarding(): void
    {
        $admin = $this->platformAdmin(['email' => 'ops@example.com']);

        $this->actingAs($admin)
            ->postJson('/api/v1/onboarding/complete', [])
            ->assertOk()
            ->assertJsonPath('message', 'Platform super admins do not require business onboarding.')
            ->assertJsonPath('user.platform_role', 'super_admin')
            ->assertJsonPath('user.current_organization_id', null);

        $this->assertNull($admin->fresh()->current_organization_id);
        $this->assertSame(0, $admin->fresh()->organizations()->count());
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

    public function test_platform_system_response_omits_raw_nrs_payloads(): void
    {
        $admin = $this->platformAdmin();
        $organization = $this->organization('System Org');

        NrsApiLog::create([
            'organization_id' => $organization->id,
            'irn' => 'IRN-SYSTEM-001',
            'endpoint' => 'api/v1/invoice/sign',
            'method' => 'POST',
            'request_payload' => ['business_id' => '[REDACTED]'],
            'response_body' => ['error' => 'Unable to sign'],
            'status_code' => 500,
            'latency_ms' => 120.5,
            'ip_address' => '10.0.0.1',
        ]);

        $response = $this->actingAs($admin)
            ->getJson('/api/v1/platform/system')
            ->assertOk();

        $failure = $response->json('data.nrs_failures.0');

        $this->assertSame('IRN-SYSTEM-001', $failure['irn']);
        $this->assertArrayNotHasKey('request_payload', $failure);
        $this->assertArrayNotHasKey('response_body', $failure);
        $this->assertArrayNotHasKey('ip_address', $failure);
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

    public function test_platform_users_can_be_filtered_by_organization_uuid(): void
    {
        $admin = $this->platformAdmin();
        $first = $this->organization('First Tenant');
        $second = $this->organization('Second Tenant');
        $firstUser = User::factory()->create(['email' => 'first-member@example.com', 'current_organization_id' => $first->id]);
        $secondUser = User::factory()->create(['email' => 'second-member@example.com', 'current_organization_id' => $second->id]);
        $firstUser->organizations()->attach($first->id, ['role' => 'owner']);
        $secondUser->organizations()->attach($second->id, ['role' => 'owner']);

        $response = $this->actingAs($admin)
            ->getJson("/api/v1/platform/users?organization_id={$first->uuid}")
            ->assertOk();

        $emails = collect($response->json('data'))->pluck('email');
        $this->assertTrue($emails->contains('first-member@example.com'));
        $this->assertFalse($emails->contains('second-member@example.com'));
    }

    public function test_platform_invoices_and_activity_can_be_filtered_by_public_ids(): void
    {
        $admin = $this->platformAdmin();
        $first = $this->organization('First Tenant');
        $second = $this->organization('Second Tenant');
        $firstUser = User::factory()->create(['email' => 'first-member@example.com', 'current_organization_id' => $first->id]);
        $secondUser = User::factory()->create(['email' => 'second-member@example.com', 'current_organization_id' => $second->id]);
        $firstUser->organizations()->attach($first->id, ['role' => 'owner']);
        $secondUser->organizations()->attach($second->id, ['role' => 'owner']);
        $firstInvoice = $this->invoice($first, 'INV-FIRST', 1000);
        $secondInvoice = $this->invoice($second, 'INV-SECOND', 2000);

        ActivityLog::create([
            'organization_id' => $first->id,
            'user_id' => $firstUser->id,
            'action' => 'invoice.created',
            'description' => 'First activity',
        ]);

        ActivityLog::create([
            'organization_id' => $second->id,
            'user_id' => $secondUser->id,
            'action' => 'invoice.created',
            'description' => 'Second activity',
        ]);

        $orgInvoices = $this->actingAs($admin)
            ->getJson("/api/v1/platform/invoices?organization_id={$first->uuid}")
            ->assertOk()
            ->json('data');
        $this->assertContains($firstInvoice->uuid, collect($orgInvoices)->pluck('id'));
        $this->assertNotContains($secondInvoice->uuid, collect($orgInvoices)->pluck('id'));

        $userInvoices = $this->actingAs($admin)
            ->getJson("/api/v1/platform/invoices?user_id={$firstUser->uuid}")
            ->assertOk()
            ->json('data');
        $this->assertContains($firstInvoice->uuid, collect($userInvoices)->pluck('id'));
        $this->assertNotContains($secondInvoice->uuid, collect($userInvoices)->pluck('id'));

        $orgActivity = $this->actingAs($admin)
            ->getJson("/api/v1/platform/activity-logs?organization_id={$first->uuid}")
            ->assertOk()
            ->json('data');
        $this->assertSame(['First activity'], collect($orgActivity)->pluck('description')->all());

        $userActivity = $this->actingAs($admin)
            ->getJson("/api/v1/platform/activity-logs?user_id={$firstUser->uuid}")
            ->assertOk()
            ->json('data');
        $this->assertSame(['First activity'], collect($userActivity)->pluck('description')->all());
    }

    public function test_platform_organization_profile_update_persists_allowed_fields_and_rejects_unsupported_fields(): void
    {
        $admin = $this->platformAdmin();
        $organization = $this->organization('Editable Org');

        $this->actingAs($admin)
            ->patchJson("/api/v1/platform/organizations/{$organization->uuid}", [
                'name' => 'Updated Editable Org',
                'tin' => '12345678-0001',
                'email' => 'updated-org@example.com',
                'telephone' => '+2348000000000',
                'street_name' => '1 Platform Road',
                'city_name' => 'Lagos',
                'postal_zone' => '100001',
                'country_code' => 'NG',
                'business_description' => 'Updated by platform support.',
                'service_id' => 'SVC-PLATFORM',
                'nrs_business_id' => 'NRS-PLATFORM',
                'platform_status' => 'active',
                'onboarding_status' => 'review',
                'verified' => true,
                'admin_notes' => 'Verified during platform review.',
                'reason' => 'Support ticket correction',
            ])
            ->assertOk()
            ->assertJsonPath('data.name', 'Updated Editable Org')
            ->assertJsonPath('data.service_id', 'SVC-PLATFORM')
            ->assertJsonPath('data.onboarding_status', 'review');

        $this->assertDatabaseHas('organizations', [
            'id' => $organization->id,
            'name' => 'Updated Editable Org',
            'service_id' => 'SVC-PLATFORM',
            'nrs_business_id' => 'NRS-PLATFORM',
        ]);

        $this->assertDatabaseHas('activity_logs', [
            'organization_id' => $organization->id,
            'user_id' => $admin->id,
            'action' => 'platform.organization.updated',
        ]);

        $this->actingAs($admin)
            ->patchJson("/api/v1/platform/organizations/{$organization->uuid}", [
                'deleted_at' => now()->toISOString(),
            ])
            ->assertUnprocessable()
            ->assertJsonValidationErrors('deleted_at');
    }

    public function test_platform_user_profile_update_persists_allowed_fields_and_rejects_unsupported_fields(): void
    {
        $admin = $this->platformAdmin();
        $user = User::factory()->create([
            'first_name' => 'Old',
            'last_name' => 'Name',
            'email_verified_at' => null,
        ]);

        $this->actingAs($admin)
            ->patchJson("/api/v1/platform/users/{$user->uuid}", [
                'first_name' => 'New',
                'last_name' => 'Name',
                'email_verified' => true,
                'suspended' => true,
                'reason' => 'Platform support update',
            ])
            ->assertOk()
            ->assertJsonPath('data.first_name', 'New')
            ->assertJsonPath('data.last_name', 'Name');

        $user->refresh();
        $this->assertSame('New', $user->first_name);
        $this->assertNotNull($user->email_verified_at);
        $this->assertNotNull($user->suspended_at);

        $this->assertDatabaseHas('activity_logs', [
            'user_id' => $admin->id,
            'action' => 'platform.user.updated',
        ]);

        $this->actingAs($admin)
            ->patchJson("/api/v1/platform/users/{$user->uuid}", [
                'password' => 'not-allowed',
            ])
            ->assertUnprocessable()
            ->assertJsonValidationErrors('password');
    }

    private function organization(string $name): Organization
    {
        return Organization::create([
            'name' => $name,
            'slug' => Str::slug($name).'-'.fake()->unique()->numberBetween(1000, 9999),
            'tin' => fake()->numerify('########-####'),
            'email' => fake()->safeEmail(),
            'nrs_business_id' => (string) Str::uuid7(),
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

        $invoice = Invoice::create([
            'organization_id' => $organization->id,
            'customer_id' => $customer->id,
            'invoice_number' => $number,
            'payment_status' => 'PENDING',
            'issue_date' => today(),
            'due_date' => today()->addDays(30),
            'document_currency_code' => 'NGN',
            'line_extension_amount' => $amount,
            'tax_exclusive_amount' => $amount,
            'tax_inclusive_amount' => $amount,
            'payable_amount' => $amount,
        ]);

        $invoice->forceFill(['status' => $status])->save();

        return $invoice;
    }
}
