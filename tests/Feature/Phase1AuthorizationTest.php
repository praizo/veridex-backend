<?php

namespace Tests\Feature;

use App\Models\Customer;
use App\Models\Organization;
use App\Models\Product;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Tests\TestCase;

class Phase1AuthorizationTest extends TestCase
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

    public function test_non_admin_cannot_remove_team_member(): void
    {
        $organization = $this->createOrganization('Team Org');
        $viewer = $this->createMember($organization, 'viewer');
        $target = $this->createMember($organization, 'editor');

        $response = $this->actingAs($viewer)
            ->deleteJson("/api/v1/team/members/{$target->uuid}");

        $response->assertStatus(403);

        $this->assertDatabaseHas('organization_user', [
            'organization_id' => $organization->id,
            'user_id' => $target->id,
            'role' => 'editor',
        ]);
    }

    public function test_admin_cannot_grant_owner_role(): void
    {
        $organization = $this->createOrganization('Role Org');
        $admin = $this->createMember($organization, 'admin');
        $target = $this->createMember($organization, 'viewer');

        $response = $this->actingAs($admin)
            ->putJson("/api/v1/team/members/{$target->uuid}", [
                'role' => 'owner',
            ]);

        $response->assertStatus(422);

        $this->assertDatabaseHas('organization_user', [
            'organization_id' => $organization->id,
            'user_id' => $target->id,
            'role' => 'viewer',
        ]);
    }

    public function test_admin_cannot_modify_or_remove_owner(): void
    {
        $organization = $this->createOrganization('Owner Org');
        $admin = $this->createMember($organization, 'admin');
        $owner = $this->createMember($organization, 'owner');

        $this->actingAs($admin)
            ->putJson("/api/v1/team/members/{$owner->uuid}", [
                'role' => 'viewer',
            ])
            ->assertStatus(403);

        $this->actingAs($admin)
            ->deleteJson("/api/v1/team/members/{$owner->uuid}")
            ->assertStatus(403);

        $this->assertDatabaseHas('organization_user', [
            'organization_id' => $organization->id,
            'user_id' => $owner->id,
            'role' => 'owner',
        ]);
    }

    public function test_user_cannot_view_another_organization_product(): void
    {
        $organization = $this->createOrganization('Visible Org');
        $otherOrganization = $this->createOrganization('Hidden Org');
        $user = $this->createMember($organization, 'admin');

        $product = Product::create([
            'organization_id' => $otherOrganization->id,
            'name' => 'Hidden Product',
            'unit_price' => 1000,
            'unit_code' => 'EA',
            'hs_code' => '123456',
            'tax_category' => 'S',
            'tax_rate' => 7.5,
        ]);

        $this->actingAs($user)
            ->getJson("/api/v1/products/{$product->uuid}")
            ->assertStatus(404);
    }

    public function test_user_cannot_create_invoice_with_another_organization_customer(): void
    {
        $organization = $this->createOrganization('Invoice Org');
        $otherOrganization = $this->createOrganization('Customer Org');
        $user = $this->createMember($organization, 'admin');

        $customer = Customer::create([
            'organization_id' => $otherOrganization->id,
            'first_name' => 'Other Customer', 'last_name' => 'Last',
            'type' => 'business',
            'tin' => '87654321-0001',
            'email' => 'other@test.com',
        ]);

        $payload = [
            'customer_id' => $customer->uuid,
            'invoice_number' => 'INV-CROSS-ORG',
            'invoice_type_code' => '380',
            'issue_date' => now()->format('Y-m-d'),
            'document_currency_code' => 'NGN',
            'legal_monetary_total' => [
                'line_extension_amount' => 1000,
                'tax_exclusive_amount' => 1000,
                'tax_inclusive_amount' => 1075,
                'payable_amount' => 1075,
            ],
            'lines' => [
                [
                    'line_id' => '1',
                    'invoiced_quantity' => 1,
                    'line_extension_amount' => 1000,
                    'item_name' => 'Test Product',
                    'price_amount' => 1000,
                    'hsn_code' => '123456',
                    'product_category' => 'Test Category',
                    'tax_category_id' => 'STANDARD_VAT',
                    'tax_percent' => 7.5,
                ],
            ],
        ];

        $this->actingAs($user)
            ->postJson('/api/v1/invoices', $payload)
            ->assertStatus(422)
            ->assertJsonValidationErrors('customer_id');
    }
}
