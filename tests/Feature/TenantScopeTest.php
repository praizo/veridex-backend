<?php

namespace Tests\Feature;

use App\Models\Customer;
use App\Models\Invoice;
use App\Models\Organization;
use App\Models\Product;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Route;
use Tests\TestCase;

class TenantScopeTest extends TestCase
{
    use RefreshDatabase;

    public function test_org_scoped_routes_automatically_filter_tenant_owned_models(): void
    {
        [$user, $organization, $otherOrganization] = $this->tenantFixture();

        Product::create($this->productData($organization, ['name' => 'Own product']));
        Product::create($this->productData($otherOrganization, ['name' => 'Other product']));

        $ownCustomer = Customer::create($this->customerData($organization, ['first_name' => 'Own']));
        $otherCustomer = Customer::create($this->customerData($otherOrganization, ['first_name' => 'Other']));

        Invoice::create($this->invoiceData($organization, $ownCustomer, ['invoice_number' => 'INV-OWN']));
        Invoice::create($this->invoiceData($otherOrganization, $otherCustomer, ['invoice_number' => 'INV-OTHER']));

        Route::middleware(['auth:sanctum', 'org.scope'])->get('/_test/tenant-scope-index', function () {
            return response()->json([
                'products' => Product::query()->orderBy('name')->pluck('name')->all(),
                'customers' => Customer::query()->orderBy('first_name')->pluck('first_name')->all(),
                'invoices' => Invoice::query()->orderBy('invoice_number')->pluck('invoice_number')->all(),
            ]);
        });

        $this->actingAs($user)
            ->getJson('/_test/tenant-scope-index')
            ->assertOk()
            ->assertJson([
                'products' => ['Own product'],
                'customers' => ['Own'],
                'invoices' => ['INV-OWN'],
            ]);
    }

    public function test_org_scoped_routes_apply_tenant_scope_to_find_queries(): void
    {
        [$user, , $otherOrganization] = $this->tenantFixture();

        $otherProduct = Product::create($this->productData($otherOrganization, ['name' => 'Other product']));

        Route::middleware(['auth:sanctum', 'org.scope'])->get('/_test/tenant-scope-find/{productId}', function (int $productId) {
            return response()->json([
                'found' => Product::find($productId) !== null,
            ]);
        });

        $this->actingAs($user)
            ->getJson("/_test/tenant-scope-find/{$otherProduct->id}")
            ->assertOk()
            ->assertJson(['found' => false]);
    }

    public function test_org_scoped_routes_auto_fill_organization_id_on_create(): void
    {
        [$user, $organization] = $this->tenantFixture();

        Route::middleware(['auth:sanctum', 'org.scope'])->post('/_test/tenant-scope-products', function () {
            $product = Product::create([
                'item_type' => 'goods',
                'name' => 'Auto scoped product',
                'description' => 'Created without manual organization assignment',
                'quantity' => 1,
                'unit_price' => 1000,
                'unit_code' => 'EA',
                'hs_code' => '998300',
                'item_category' => 'Scoped category',
                'tax_category' => 'STANDARD_VAT',
                'tax_rate' => 7.5,
            ]);

            return response()->json(['organization_id' => $product->organization_id]);
        });

        $this->actingAs($user)
            ->postJson('/_test/tenant-scope-products')
            ->assertOk()
            ->assertJson(['organization_id' => $organization->id]);
    }

    public function test_tenant_context_is_cleared_after_org_scoped_request(): void
    {
        [$user, $organization, $otherOrganization] = $this->tenantFixture();

        Product::create($this->productData($organization, ['name' => 'Own product']));
        Product::create($this->productData($otherOrganization, ['name' => 'Other product']));

        Route::middleware(['auth:sanctum', 'org.scope'])->get('/_test/tenant-scope-count', function () {
            return response()->json(['count' => Product::count()]);
        });

        $this->actingAs($user)
            ->getJson('/_test/tenant-scope-count')
            ->assertOk()
            ->assertJson(['count' => 1]);

        $this->assertSame(2, Product::count());
    }

    private function tenantFixture(): array
    {
        $organization = Organization::create([
            'name' => 'Tenant Org',
            'slug' => 'tenant-org',
            'tin' => '12345678-0001',
            'email' => 'tenant@test.com',
            'nrs_business_id' => '0d70f6d2-ac1a-4261-b778-2825859d76c8',
        ]);

        $otherOrganization = Organization::create([
            'name' => 'Other Org',
            'slug' => 'other-org',
            'tin' => '22222222-0001',
            'email' => 'other@test.com',
            'nrs_business_id' => 'a3e7f7f2-57b2-4ef2-8f1d-464cbba2c111',
        ]);

        $user = User::factory()->create([
            'current_organization_id' => $organization->id,
            'onboarding_completed_at' => now(),
        ]);

        $user->organizations()->attach($organization->id, ['role' => 'admin']);

        return [$user, $organization, $otherOrganization];
    }

    private function productData(Organization $organization, array $overrides = []): array
    {
        return array_merge([
            'organization_id' => $organization->id,
            'item_type' => 'goods',
            'name' => 'Scoped product',
            'description' => 'Scoped product description',
            'quantity' => 1,
            'unit_price' => 1000,
            'unit_code' => 'EA',
            'hs_code' => '998300',
            'item_category' => 'Scoped category',
            'tax_category' => 'STANDARD_VAT',
            'tax_rate' => 7.5,
        ], $overrides);
    }

    private function customerData(Organization $organization, array $overrides = []): array
    {
        return array_merge([
            'organization_id' => $organization->id,
            'first_name' => 'Scoped',
            'last_name' => 'Customer',
            'type' => 'business',
            'tin' => '87654321-0001',
            'email' => 'customer@test.com',
        ], $overrides);
    }

    private function invoiceData(Organization $organization, Customer $customer, array $overrides = []): array
    {
        return array_merge([
            'organization_id' => $organization->id,
            'customer_id' => $customer->id,
            'invoice_number' => 'INV-SCOPED',
            'invoice_type_code' => '380',
            'invoice_kind' => 'B2B',
            'issue_date' => now()->format('Y-m-d'),
            'document_currency_code' => 'NGN',
            'line_extension_amount' => 1000,
            'tax_exclusive_amount' => 1000,
            'tax_inclusive_amount' => 1075,
            'payable_amount' => 1075,
            'allowance_total_amount' => 0,
            'charge_total_amount' => 0,
            'prepaid_amount' => 0,
            'payable_rounding_amount' => 0,
        ], $overrides);
    }
}
