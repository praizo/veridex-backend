<?php

namespace Tests\Feature;

use App\Models\ActivityLog;
use App\Models\Customer;
use App\Models\Organization;
use App\Models\Product;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class UserActivityAuditTest extends TestCase
{
    use RefreshDatabase;

    public function test_tenant_user_actions_write_activity_logs(): void
    {
        [$organization, $owner] = $this->owner();

        $this->actingAs($owner)
            ->patchJson('/api/v1/profile', [
                'first_name' => 'Audit',
                'last_name' => 'Owner',
            ])
            ->assertOk();

        $this->actingAs($owner)
            ->patchJson("/api/v1/organizations/{$organization->uuid}", [
                'name' => 'Audited Organization',
                'tin' => $organization->tin,
                'nrs_business_id' => $organization->nrs_business_id,
                'email' => $organization->email,
                'telephone' => $organization->telephone,
                'street_name' => $organization->street_name,
                'city_name' => $organization->city_name,
                'postal_zone' => $organization->postal_zone,
                'country_code' => $organization->country_code,
                'business_description' => $organization->business_description,
                'service_id' => $organization->service_id,
            ])
            ->assertOk();

        $this->actingAs($owner)
            ->postJson('/api/v1/customers', [
                'first_name' => 'Jane',
                'last_name' => 'Customer',
                'type' => 'business',
                'tin' => '12345678',
                'email' => 'jane.customer@example.com',
            ])
            ->assertCreated();

        $this->actingAs($owner)
            ->postJson('/api/v1/products', [
                'name' => 'Audit Product',
                'item_type' => 'goods',
                'price' => 2500,
                'unit' => 'EA',
                'hsn_code' => '010121',
                'item_category' => 'STANDARD_VAT',
                'product_category' => 'STANDARD_VAT',
                'tax_rate' => 7.5,
            ])
            ->assertCreated();

        $this->actingAs($owner)
            ->postJson('/api/v1/team/members', [
                'first_name' => 'Team',
                'last_name' => 'Member',
                'email' => 'team.member@example.com',
                'role' => 'viewer',
            ])
            ->assertCreated();

        $this->actingAs($owner)
            ->get('/api/v1/customers/export/csv')
            ->assertOk();

        $this->actingAs($owner)
            ->get('/api/v1/reports/invoices/csv')
            ->assertOk();

        $actions = ActivityLog::query()
            ->where('organization_id', $organization->id)
            ->pluck('action')
            ->all();

        $this->assertContains('profile.updated', $actions);
        $this->assertContains('organization.updated', $actions);
        $this->assertContains('customer.created', $actions);
        $this->assertContains('product.created', $actions);
        $this->assertContains('team.member.added', $actions);
        $this->assertContains('customer.exported', $actions);
        $this->assertContains('report.invoices.exported', $actions);
    }

    public function test_customer_product_and_team_changes_write_activity_logs(): void
    {
        [$organization, $owner] = $this->owner();
        $customer = Customer::factory()->create(['organization_id' => $organization->id]);
        $product = Product::factory()->create(['organization_id' => $organization->id]);
        $member = User::factory()->create(['current_organization_id' => $organization->id]);
        $member->organizations()->attach($organization->id, ['role' => 'viewer']);

        $this->actingAs($owner)
            ->putJson("/api/v1/customers/{$customer->uuid}", [
                'first_name' => 'Updated',
                'last_name' => 'Customer',
                'type' => 'business',
                'tin' => $customer->tin,
                'email' => $customer->email,
            ])
            ->assertOk();

        $this->actingAs($owner)
            ->putJson("/api/v1/products/{$product->uuid}", [
                'name' => 'Updated Product',
                'item_type' => 'goods',
                'price' => 3500,
                'unit' => 'EA',
                'hsn_code' => '010121',
                'item_category' => 'STANDARD_VAT',
                'product_category' => 'STANDARD_VAT',
                'tax_rate' => 7.5,
            ])
            ->assertOk();

        $this->actingAs($owner)
            ->putJson("/api/v1/team/members/{$member->uuid}", [
                'role' => 'editor',
            ])
            ->assertOk();

        $this->actingAs($owner)
            ->deleteJson("/api/v1/team/members/{$member->uuid}")
            ->assertOk();

        $this->actingAs($owner)
            ->deleteJson("/api/v1/customers/{$customer->uuid}")
            ->assertOk();

        $this->actingAs($owner)
            ->deleteJson("/api/v1/products/{$product->uuid}")
            ->assertOk();

        $actions = ActivityLog::query()
            ->where('organization_id', $organization->id)
            ->pluck('action')
            ->all();

        $this->assertContains('customer.updated', $actions);
        $this->assertContains('product.updated', $actions);
        $this->assertContains('team.member.role_changed', $actions);
        $this->assertContains('team.member.removed', $actions);
        $this->assertContains('customer.deleted', $actions);
        $this->assertContains('product.deleted', $actions);
    }

    private function owner(): array
    {
        $organization = Organization::factory()->create();
        $user = User::factory()->create([
            'current_organization_id' => $organization->id,
            'onboarding_completed_at' => now(),
            'email_verified_at' => now(),
        ]);
        $user->organizations()->attach($organization->id, ['role' => 'owner']);

        return [$organization, $user];
    }
}
