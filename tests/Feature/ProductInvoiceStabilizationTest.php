<?php

namespace Tests\Feature;

use App\Models\Customer;
use App\Models\Invoice;
use App\Models\Organization;
use App\Models\Product;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ProductInvoiceStabilizationTest extends TestCase
{
    use RefreshDatabase;

    protected User $user;

    protected Organization $organization;

    protected Customer $customer;

    protected function setUp(): void
    {
        parent::setUp();

        $this->organization = Organization::create([
            'name' => 'Product Org',
            'slug' => 'product-org',
            'tin' => '12345678-0001',
            'email' => 'product@test.com',
            'nrs_business_id' => '0d70f6d2-ac1a-4261-b778-2825859d76c8',
        ]);

        $this->user = User::factory()->create([
            'current_organization_id' => $this->organization->id,
            'onboarding_completed_at' => now(),
        ]);

        $this->user->organizations()->attach($this->organization->id, ['role' => 'admin']);

        $this->customer = Customer::create([
            'organization_id' => $this->organization->id,
            'name' => 'Business Buyer',
            'type' => 'business',
            'tin' => '87654321-0001',
            'email' => 'buyer@test.com',
        ]);
    }

    public function test_cross_organization_product_access_is_blocked(): void
    {
        $otherProduct = $this->productForOtherOrganization();

        $this->actingAs($this->user)
            ->getJson("/api/v1/products/{$otherProduct->uuid}")
            ->assertNotFound();
    }

    public function test_product_update_is_scoped_by_organization(): void
    {
        $ownProduct = $this->product('Managed Service');
        $otherProduct = $this->productForOtherOrganization();

        $this->actingAs($this->user)
            ->putJson("/api/v1/products/{$ownProduct->uuid}", $this->productPayload([
                'name' => 'Updated Managed Service',
                'price' => 2500,
            ]))
            ->assertOk()
            ->assertJsonPath('data.name', 'Updated Managed Service')
            ->assertJsonPath('data.price', '2500.00');

        $this->actingAs($this->user)
            ->putJson("/api/v1/products/{$otherProduct->uuid}", $this->productPayload([
                'name' => 'Cross Tenant Update',
            ]))
            ->assertNotFound();

        $this->assertSame('Other Product', $otherProduct->fresh()->name);
    }

    public function test_product_delete_is_scoped_and_soft_deletes_referenced_values(): void
    {
        $product = $this->product('Reusable Advisory');
        $otherProduct = $this->productForOtherOrganization();

        $this->createInvoiceFromProduct($product)
            ->assertStatus(201);

        $this->actingAs($this->user)
            ->deleteJson("/api/v1/products/{$otherProduct->uuid}")
            ->assertNotFound();

        $this->actingAs($this->user)
            ->deleteJson("/api/v1/products/{$product->uuid}")
            ->assertOk();

        $this->assertSoftDeleted('products', ['id' => $product->id]);

        $invoice = Invoice::with('lines')->firstOrFail();
        $this->assertSame('Reusable Advisory', $invoice->lines->first()->item_name);
    }

    public function test_product_values_are_accepted_by_invoice_line_creation(): void
    {
        $productResponse = $this->actingAs($this->user)
            ->postJson('/api/v1/products', $this->productPayload([
                'name' => 'Monthly Retainer',
                'description' => 'Managed compliance support',
                'quantity' => 3,
                'price' => 45000,
                'hsn_code' => '998314',
                'item_category' => 'Accounting and compliance services',
                'product_category' => 'STANDARD_VAT',
                'tax_rate' => 7.5,
            ]));

        $productResponse->assertStatus(201)
            ->assertJsonPath('data.name', 'Monthly Retainer')
            ->assertJsonPath('data.quantity', '3.00')
            ->assertJsonPath('data.unit', 'EA')
            ->assertJsonPath('data.hsn_code', '998314')
            ->assertJsonPath('data.item_category', 'Accounting and compliance services')
            ->assertJsonPath('data.product_category', 'STANDARD_VAT')
            ->assertJsonPath('data.tax_rate', '7.50');

        $product = Product::firstOrFail();

        $this->createInvoiceFromProduct($product)
            ->assertStatus(201)
            ->assertJsonPath('data.lines.0.item_name', 'Monthly Retainer')
            ->assertJsonPath('data.lines.0.item_description', 'Managed compliance support')
            ->assertJsonPath('data.lines.0.item_category', 'Accounting and compliance services')
            ->assertJsonPath('data.lines.0.invoiced_quantity', '3.00')
            ->assertJsonPath('data.lines.0.unit_code', 'EA')
            ->assertJsonPath('data.lines.0.tax_category_id', 'STANDARD_VAT')
            ->assertJsonPath('data.lines.0.tax_percent', '7.50');

        $this->assertDatabaseHas('invoice_lines', [
            'item_name' => 'Monthly Retainer',
            'hs_code' => '998314',
            'item_category' => 'Accounting and compliance services',
            'invoiced_quantity' => 3,
            'unit_code' => 'EA',
            'tax_category_id' => 'STANDARD_VAT',
        ]);
    }

    public function test_product_and_invoice_quantities_must_be_whole_numbers(): void
    {
        $this->actingAs($this->user)
            ->postJson('/api/v1/products', $this->productPayload([
                'quantity' => 1.5,
            ]))
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['quantity']);

        $product = $this->product('Whole Number Service');

        $this->createInvoiceFromProduct($product, 1.5)
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['lines.0.invoiced_quantity']);
    }

    private function product(string $name): Product
    {
        return Product::create([
            'organization_id' => $this->organization->id,
            'name' => $name,
            'description' => "{$name} description",
            'quantity' => 1,
            'unit_price' => 1500,
            'unit_code' => 'EA',
            'hs_code' => '998300',
            'item_category' => "{$name} category",
            'tax_category' => 'STANDARD_VAT',
            'tax_rate' => 7.5,
        ]);
    }

    private function productForOtherOrganization(): Product
    {
        $organization = Organization::create([
            'name' => 'Other Org',
            'slug' => 'other-org',
            'tin' => '22222222-0001',
            'email' => 'other@test.com',
            'nrs_business_id' => 'a3e7f7f2-57b2-4ef2-8f1d-464cbba2c111',
        ]);

        return Product::create([
            'organization_id' => $organization->id,
            'name' => 'Other Product',
            'description' => 'Other tenant product',
            'quantity' => 1,
            'unit_price' => 1000,
            'unit_code' => 'EA',
            'hs_code' => '998300',
            'item_category' => 'Other category',
            'tax_category' => 'STANDARD_VAT',
            'tax_rate' => 7.5,
        ]);
    }

    private function productPayload(array $overrides = []): array
    {
        return array_merge([
            'name' => 'Consulting Package',
            'description' => 'Standard consulting package',
            'quantity' => 1,
            'price' => 1500,
            'hsn_code' => '998300',
            'item_category' => 'Standard consulting category',
            'product_category' => 'STANDARD_VAT',
            'tax_rate' => 7.5,
        ], $overrides);
    }

    private function createInvoiceFromProduct(Product $product, ?float $quantityOverride = null)
    {
        $quantity = $quantityOverride ?? (float) ($product->quantity ?? 1);
        $price = (float) $product->unit_price;
        $lineTotal = round($quantity * $price, 2);
        $tax = round($lineTotal * ((float) $product->tax_rate / 100), 2);

        return $this->actingAs($this->user)->postJson('/api/v1/invoices', [
            'customer_id' => $this->customer->uuid,
            'invoice_type_code' => '380',
            'invoice_kind' => 'B2B',
            'issue_date' => now()->format('Y-m-d'),
            'document_currency_code' => 'NGN',
            'payment_status' => 'PENDING',
            'payment_means' => [
                ['payment_means_code' => '30'],
            ],
            'legal_monetary_total' => [
                'line_extension_amount' => $lineTotal,
                'tax_exclusive_amount' => $lineTotal,
                'tax_inclusive_amount' => $lineTotal + $tax,
                'payable_amount' => $lineTotal + $tax,
            ],
            'lines' => [
                [
                    'line_id' => '1',
                    'item_name' => $product->name,
                    'item_description' => $product->description,
                    'hsn_code' => $product->hs_code,
                    'product_category' => $product->item_category,
                    'invoiced_quantity' => $quantity,
                    'line_extension_amount' => $lineTotal,
                    'price_amount' => $price,
                    'price_unit' => $product->unit_code,
                    'tax_category_id' => $product->tax_category,
                    'tax_percent' => (float) $product->tax_rate,
                ],
            ],
            'tax_totals' => [
                [
                    'tax_amount' => $tax,
                    'taxable_amount' => $lineTotal,
                    'tax_category_id' => $product->tax_category,
                    'tax_percent' => (float) $product->tax_rate,
                ],
            ],
        ]);
    }
}
