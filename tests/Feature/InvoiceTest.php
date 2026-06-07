<?php

namespace Tests\Feature;

use App\Models\Customer;
use App\Models\Invoice;
use App\Models\Organization;
use App\Models\User;
use App\Services\Nrs\NrsInvoiceService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Mockery\MockInterface;
use Tests\TestCase;

class InvoiceTest extends TestCase
{
    use RefreshDatabase;

    protected User $user;

    protected Organization $organization;

    protected Customer $customer;

    protected function setUp(): void
    {
        parent::setUp();

        $this->organization = Organization::create([
            'name' => 'Test Org',
            'slug' => 'test-org',
            'tin' => '12345678-0001',
            'email' => 'org@test.com',
            'nrs_business_id' => '0d70f6d2-ac1a-4261-b778-2825859d76c8',
        ]);

        $this->user = User::factory()->create([
            'current_organization_id' => $this->organization->id,
            'onboarding_completed_at' => now(),
        ]);

        $this->user->organizations()->attach($this->organization->id, ['role' => 'admin']);

        $this->customer = Customer::create([
            'organization_id' => $this->organization->id,
            'name' => 'Test Customer',
            'type' => 'individual', // individual -> B2C
            'tin' => '87654321-0001',
            'email' => 'cust@test.com',
        ]);
    }

    public function test_can_create_invoice_with_explicit_invoice_kind(): void
    {
        $payload = [
            'customer_id' => $this->customer->uuid,
            'invoice_number' => 'INV-001',
            'invoice_type_code' => '380',
            'invoice_kind' => 'B2B',
            'issue_date' => now()->format('Y-m-d'),
            'document_currency_code' => 'NGN',
            'payment_status' => 'PENDING',
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
            'tax_totals' => [
                [
                    'tax_amount' => 75,
                    'taxable_amount' => 1000,
                    'tax_category_id' => 'STANDARD_VAT',
                    'tax_percent' => 7.5,
                ],
            ],
        ];

        $response = $this->actingAs($this->user)
            ->postJson('/api/v1/invoices', $payload);

        $response->assertStatus(201)
            ->assertJsonPath('data.invoice_kind', 'B2B');

        $this->assertDatabaseHas('invoices', [
            'invoice_number' => 'INV-001',
            'invoice_kind' => 'B2B',
        ]);
    }

    public function test_creates_invoice_with_defaulted_invoice_kind_based_on_customer_type(): void
    {
        $payload = [
            'customer_id' => $this->customer->uuid,
            'invoice_number' => 'INV-002',
            'invoice_type_code' => '380',
            // invoice_kind is intentionally omitted
            'issue_date' => now()->format('Y-m-d'),
            'document_currency_code' => 'NGN',
            'payment_status' => 'PENDING',
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
            'tax_totals' => [
                [
                    'tax_amount' => 75,
                    'taxable_amount' => 1000,
                    'tax_category_id' => 'STANDARD_VAT',
                    'tax_percent' => 7.5,
                ],
            ],
        ];

        $response = $this->actingAs($this->user)
            ->postJson('/api/v1/invoices', $payload);

        // Customer type is 'individual' -> invoice_kind should default to B2C
        $response->assertStatus(201)
            ->assertJsonPath('data.invoice_kind', 'B2C');

        $this->assertDatabaseHas('invoices', [
            'invoice_number' => 'INV-002',
            'invoice_kind' => 'B2C',
        ]);
    }

    public function test_can_update_payment_status_locally_for_draft_invoice(): void
    {
        $invoice = Invoice::create([
            'organization_id' => $this->organization->id,
            'customer_id' => $this->customer->id,
            'invoice_number' => 'INV-003',
            'status' => 'draft',
            'payment_status' => 'PENDING',
            'issue_date' => now(),
            'line_extension_amount' => 1000,
            'tax_exclusive_amount' => 1000,
            'tax_inclusive_amount' => 1075,
            'payable_amount' => 1075,
        ]);

        $response = $this->actingAs($this->user)
            ->patchJson("/api/v1/invoices/{$invoice->uuid}/payment", [
                'payment_status' => 'PAID',
            ]);

        $response->assertStatus(200)
            ->assertJsonPath('status', 'PAID');

        $this->assertEquals('PAID', $invoice->fresh()->payment_status->value);
    }

    public function test_can_update_payment_status_on_nrs_for_signed_invoice(): void
    {
        $invoice = Invoice::create([
            'organization_id' => $this->organization->id,
            'customer_id' => $this->customer->id,
            'invoice_number' => 'INV-004',
            'status' => 'signed',
            'payment_status' => 'PENDING',
            'issue_date' => now(),
            'irn' => 'TEST-IRN-12345',
            'line_extension_amount' => 1000,
            'tax_exclusive_amount' => 1000,
            'tax_inclusive_amount' => 1075,
            'payable_amount' => 1075,
        ]);

        $this->mock(NrsInvoiceService::class, function (MockInterface $mock) use ($invoice) {
            $mock->shouldReceive('updatePayment')
                ->once()
                ->with(\Mockery::on(function ($arg) use ($invoice) {
                    return $arg->id === $invoice->id;
                }), 'PAID', 'REF123')
                ->andReturn(['status' => 'success']);
        });

        $response = $this->actingAs($this->user)
            ->patchJson("/api/v1/invoices/{$invoice->uuid}/payment", [
                'payment_status' => 'PAID',
                'reference' => 'REF123',
            ]);

        $response->assertStatus(200)
            ->assertJsonPath('status', 'PAID');

        $this->assertEquals('PAID', $invoice->fresh()->payment_status->value);
    }

    public function test_updating_payment_status_to_pending_does_not_call_nrs(): void
    {
        $invoice = Invoice::create([
            'organization_id' => $this->organization->id,
            'customer_id' => $this->customer->id,
            'invoice_number' => 'INV-005',
            'status' => 'signed',
            'payment_status' => 'PAID',
            'issue_date' => now(),
            'irn' => 'TEST-IRN-12346',
            'line_extension_amount' => 1000,
            'tax_exclusive_amount' => 1000,
            'tax_inclusive_amount' => 1075,
            'payable_amount' => 1075,
        ]);

        $this->mock(NrsInvoiceService::class, function (MockInterface $mock) {
            $mock->shouldNotReceive('updatePayment');
        });

        $response = $this->actingAs($this->user)
            ->patchJson("/api/v1/invoices/{$invoice->uuid}/payment", [
                'payment_status' => 'PENDING',
            ]);

        $response->assertStatus(200)
            ->assertJsonPath('status', 'PENDING');

        $this->assertEquals('PENDING', $invoice->fresh()->payment_status->value);
    }

    public function test_nrs_payment_update_handles_terminal_state_gracefully(): void
    {
        $invoice = Invoice::create([
            'organization_id' => $this->organization->id,
            'customer_id' => $this->customer->id,
            'invoice_number' => 'INV-006',
            'status' => 'signed',
            'payment_status' => 'PENDING',
            'issue_date' => now(),
            'irn' => 'TEST-IRN-12347',
            'line_extension_amount' => 1000,
            'tax_exclusive_amount' => 1000,
            'tax_inclusive_amount' => 1075,
            'payable_amount' => 1075,
        ]);

        Http::fake([
            '*/api/v1/invoice/update/*' => Http::response([
                'code' => 400,
                'data' => null,
                'message' => 'error has occurred',
                'error' => [
                    'id' => '3a99f05d-9a6d-4b66-af3d-fa7623d8dd90',
                    'handler' => 'invoice_actions',
                    'details' => 'invoice is in a terminal state and cannot be updated',
                    'public_message' => 'invoice is in a terminal state and cannot be updated',
                ],
            ], 400),
        ]);

        $response = $this->actingAs($this->user)
            ->patchJson("/api/v1/invoices/{$invoice->uuid}/payment", [
                'payment_status' => 'PAID',
                'reference' => 'REF123',
            ]);

        $response->assertStatus(400);

        $this->assertEquals('PENDING', $invoice->fresh()->payment_status->value);

        $this->assertDatabaseHas('activity_logs', [
            'subject_id' => $invoice->id,
            'action' => 'NRS_PAYMENT_UPDATE_SKIP',
        ]);
    }
}
