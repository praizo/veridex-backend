<?php

namespace Tests\Feature;

use App\Enums\InvoiceStatus;
use App\Exceptions\InvoiceStateException;
use App\Models\Customer;
use App\Models\Invoice;
use App\Models\Organization;
use App\Models\User;
use App\Services\InvoiceStateService;
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
            'invoice_number' => 'INV-'.now()->format('Y').'-000001',
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
            'invoice_number' => 'INV-'.now()->format('Y').'-000001',
            'invoice_kind' => 'B2C',
        ]);
    }

    public function test_invoice_numbers_are_server_controlled_and_sequential(): void
    {
        $firstPayload = $this->validInvoicePayload([
            'invoice_number' => 'CLIENT-CANNOT-SET-1',
        ]);
        $secondPayload = $this->validInvoicePayload([
            'invoice_number' => 'CLIENT-CANNOT-SET-2',
        ]);

        $firstResponse = $this->actingAs($this->user)->postJson('/api/v1/invoices', $firstPayload);
        $secondResponse = $this->actingAs($this->user)->postJson('/api/v1/invoices', $secondPayload);

        $period = now()->format('Y');

        $firstResponse->assertStatus(201)
            ->assertJsonPath('data.invoice_number', "INV-{$period}-000001");
        $secondResponse->assertStatus(201)
            ->assertJsonPath('data.invoice_number', "INV-{$period}-000002");

        $this->assertDatabaseMissing('invoices', ['invoice_number' => 'CLIENT-CANNOT-SET-1']);
        $this->assertDatabaseMissing('invoices', ['invoice_number' => 'CLIENT-CANNOT-SET-2']);
    }

    public function test_client_supplied_totals_are_recalculated_server_side(): void
    {
        $payload = $this->validInvoicePayload([
            'legal_monetary_total' => [
                'line_extension_amount' => 1,
                'tax_exclusive_amount' => 1,
                'tax_inclusive_amount' => 1,
                'payable_amount' => 1,
            ],
            'tax_totals' => [
                [
                    'tax_amount' => 1,
                    'taxable_amount' => 1,
                    'tax_category_id' => 'STANDARD_VAT',
                    'tax_percent' => 7.5,
                ],
            ],
            'lines' => [
                [
                    'line_id' => '1',
                    'invoiced_quantity' => 2,
                    'line_extension_amount' => 1,
                    'item_name' => 'Server Priced Product',
                    'price_amount' => 2000,
                    'hsn_code' => '123456',
                    'product_category' => 'Test Category',
                    'tax_category_id' => 'STANDARD_VAT',
                    'tax_percent' => 7.5,
                ],
            ],
        ]);

        $response = $this->actingAs($this->user)->postJson('/api/v1/invoices', $payload);

        $response->assertStatus(201)
            ->assertJsonPath('data.line_extension_amount', 4000)
            ->assertJsonPath('data.tax_exclusive_amount', 4000)
            ->assertJsonPath('data.tax_inclusive_amount', 4300)
            ->assertJsonPath('data.payable_amount', 4300);

        $invoice = Invoice::firstOrFail();
        $this->assertSame('4000.00', $invoice->lines()->firstOrFail()->line_extension_amount);
        $this->assertSame('300.00', $invoice->taxTotals()->firstOrFail()->tax_amount);
    }

    public function test_signed_invoice_snapshots_do_not_change_when_master_data_changes(): void
    {
        $this->actingAs($this->user)->postJson('/api/v1/invoices', $this->validInvoicePayload())
            ->assertStatus(201);

        $invoice = Invoice::with(['customer', 'lines', 'taxTotals', 'organization'])->firstOrFail();
        $stateService = app(InvoiceStateService::class);

        $stateService->transition($invoice, InvoiceStatus::PENDING_VALIDATION, $this->user, 'test');
        $stateService->transition($invoice->fresh(), InvoiceStatus::VALIDATED, $this->user, 'test');
        $stateService->transition($invoice->fresh(), InvoiceStatus::PENDING_SIGNING, $this->user, 'test');
        $stateService->transition($invoice->fresh(), InvoiceStatus::SIGNED, $this->user, 'test');

        $signedInvoice = $invoice->fresh();
        $buyerSnapshot = $signedInvoice->buyer_snapshot;
        $lineSnapshot = $signedInvoice->line_snapshot;
        $taxSnapshot = $signedInvoice->tax_snapshot;

        $this->customer->update(['name' => 'Changed Customer']);
        $signedInvoice->lines()->first()->update(['item_name' => 'Changed Line']);
        $signedInvoice->taxTotals()->first()->update(['tax_amount' => 999]);

        $unchangedInvoice = $signedInvoice->fresh();
        $this->assertSame($buyerSnapshot['name'], $unchangedInvoice->buyer_snapshot['name']);
        $this->assertSame($lineSnapshot[0]['item_name'], $unchangedInvoice->line_snapshot[0]['item_name']);
        $this->assertSame($taxSnapshot[0]['tax_amount'], $unchangedInvoice->tax_snapshot[0]['tax_amount']);
    }

    public function test_download_for_signed_invoice_uses_local_pdf_generation(): void
    {
        Http::fake([
            '*' => Http::response(['message' => 'NRS should not be called for local PDF download'], 500),
        ]);

        $invoice = Invoice::create([
            'organization_id' => $this->organization->id,
            'customer_id' => $this->customer->id,
            'invoice_number' => 'INV-2026-000001',
            'status' => 'signed',
            'payment_status' => 'PENDING',
            'issue_date' => now(),
            'irn' => 'TEST-IRN-LOCAL-PDF',
            'line_extension_amount' => 1000,
            'tax_exclusive_amount' => 1000,
            'tax_inclusive_amount' => 1075,
            'payable_amount' => 1075,
        ]);

        $response = $this->actingAs($this->user)
            ->get("/api/v1/invoices/{$invoice->uuid}/download");

        $response->assertOk()
            ->assertHeader('Content-Type', 'application/pdf');

        $invoice = $invoice->fresh();
        $this->assertNotNull($invoice->pdf_hash);
        $this->assertNull($invoice->official_pdf_path);
        $this->assertNull($invoice->official_pdf_hash);
        $this->assertNull($invoice->official_xml_path);
        $this->assertNull($invoice->official_xml_hash);

        Http::assertNothingSent();
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

    public function test_draft_and_pre_sign_validation_failed_invoices_are_editable(): void
    {
        $createResponse = $this->actingAs($this->user)
            ->postJson('/api/v1/invoices', $this->validInvoicePayload());

        $createResponse->assertStatus(201);
        $invoice = Invoice::firstOrFail();

        $this->actingAs($this->user)
            ->putJson("/api/v1/invoices/{$invoice->uuid}", $this->validInvoicePayload([
                'lines' => [[
                    'line_id' => '1',
                    'invoiced_quantity' => 1,
                    'line_extension_amount' => 1500,
                    'item_name' => 'Edited Draft Product',
                    'price_amount' => 1500,
                    'hsn_code' => '123456',
                    'product_category' => 'Test Category',
                    'tax_category_id' => 'STANDARD_VAT',
                    'tax_percent' => 7.5,
                ]],
            ]))
            ->assertOk()
            ->assertJsonPath('data.status', 'draft');

        $invoice->fresh()->update(['status' => InvoiceStatus::VALIDATION_FAILED]);

        $this->actingAs($this->user)
            ->putJson("/api/v1/invoices/{$invoice->uuid}", $this->validInvoicePayload([
                'lines' => [[
                    'line_id' => '1',
                    'invoiced_quantity' => 1,
                    'line_extension_amount' => 2000,
                    'item_name' => 'Corrected Validation Failure',
                    'price_amount' => 2000,
                    'hsn_code' => '123456',
                    'product_category' => 'Test Category',
                    'tax_category_id' => 'STANDARD_VAT',
                    'tax_percent' => 7.5,
                ]],
            ]))
            ->assertOk()
            ->assertJsonPath('data.status', 'draft');
    }

    public function test_signed_and_transmitted_invoices_are_immutable_documents(): void
    {
        $this->actingAs($this->user)
            ->postJson('/api/v1/invoices', $this->validInvoicePayload())
            ->assertStatus(201);

        $invoice = Invoice::with(['customer', 'lines', 'taxTotals', 'organization'])->firstOrFail();
        $stateService = app(InvoiceStateService::class);
        $stateService->transition($invoice, InvoiceStatus::PENDING_VALIDATION, $this->user, 'test');
        $stateService->transition($invoice->fresh(), InvoiceStatus::VALIDATED, $this->user, 'test');
        $stateService->transition($invoice->fresh(), InvoiceStatus::PENDING_SIGNING, $this->user, 'test');
        $stateService->transition($invoice->fresh(), InvoiceStatus::SIGNED, $this->user, 'test');

        $this->actingAs($this->user)
            ->putJson("/api/v1/invoices/{$invoice->uuid}", $this->validInvoicePayload())
            ->assertStatus(409);

        $invoice->fresh()->update(['status' => InvoiceStatus::TRANSMITTED]);

        $this->actingAs($this->user)
            ->putJson("/api/v1/invoices/{$invoice->uuid}", $this->validInvoicePayload())
            ->assertStatus(409);
    }

    public function test_validation_failed_after_signing_is_not_editable(): void
    {
        $this->actingAs($this->user)
            ->postJson('/api/v1/invoices', $this->validInvoicePayload())
            ->assertStatus(201);

        $invoice = Invoice::with(['customer', 'lines', 'taxTotals', 'organization'])->firstOrFail();
        $stateService = app(InvoiceStateService::class);
        $stateService->transition($invoice, InvoiceStatus::PENDING_VALIDATION, $this->user, 'test');
        $stateService->transition($invoice->fresh(), InvoiceStatus::VALIDATED, $this->user, 'test');
        $stateService->transition($invoice->fresh(), InvoiceStatus::PENDING_SIGNING, $this->user, 'test');
        $stateService->transition($invoice->fresh(), InvoiceStatus::SIGNED, $this->user, 'test');

        $invoice->fresh()->update(['status' => InvoiceStatus::VALIDATION_FAILED]);

        $this->actingAs($this->user)
            ->putJson("/api/v1/invoices/{$invoice->uuid}", $this->validInvoicePayload())
            ->assertStatus(409);
    }

    public function test_transmitted_is_terminal_and_failed_states_retry_forward_only(): void
    {
        $invoice = Invoice::create([
            'organization_id' => $this->organization->id,
            'customer_id' => $this->customer->id,
            'invoice_number' => 'INV-STATE-001',
            'status' => 'draft',
            'payment_status' => 'PENDING',
            'issue_date' => now(),
            'line_extension_amount' => 1000,
            'tax_exclusive_amount' => 1000,
            'tax_inclusive_amount' => 1075,
            'payable_amount' => 1075,
        ]);

        $stateService = app(InvoiceStateService::class);
        $stateService->transition($invoice, InvoiceStatus::PENDING_VALIDATION, $this->user, 'test');
        $stateService->transition($invoice->fresh(), InvoiceStatus::VALIDATION_FAILED, $this->user, 'test');
        $stateService->transition($invoice->fresh(), InvoiceStatus::PENDING_VALIDATION, $this->user, 'retry validation');

        $invoice->fresh()->update(['status' => InvoiceStatus::SIGN_FAILED]);
        $stateService->transition($invoice->fresh(), InvoiceStatus::PENDING_SIGNING, $this->user, 'retry signing');

        $invoice->fresh()->update(['status' => InvoiceStatus::TRANSMIT_FAILED]);
        $stateService->transition($invoice->fresh(), InvoiceStatus::PENDING_TRANSMIT, $this->user, 'retry transmit');

        $invoice->fresh()->update(['status' => InvoiceStatus::TRANSMITTED]);

        $this->expectException(InvoiceStateException::class);
        $stateService->transition($invoice->fresh(), InvoiceStatus::CONFIRMED, $this->user, 'confirm should be deferred');
    }

    public function test_validate_sign_transmit_flow_stops_at_signed_before_transmission(): void
    {
        $this->actingAs($this->user)
            ->postJson('/api/v1/invoices', $this->validInvoicePayload())
            ->assertStatus(201);

        Http::fake([
            '*/api/v1/invoice/validate' => Http::response(['message' => 'validated'], 200),
            '*/api/v1/invoice/sign' => Http::response(['irn' => 'SIGNED-IRN-001'], 200),
            '*/api/v1/invoice/transmit/*' => Http::response(['message' => 'transmitted'], 200),
        ]);

        $invoice = Invoice::firstOrFail();

        $this->actingAs($this->user)
            ->postJson("/api/v1/invoices/{$invoice->uuid}/validate")
            ->assertOk()
            ->assertJsonPath('data.status', 'validated');

        $this->actingAs($this->user)
            ->postJson("/api/v1/invoices/{$invoice->uuid}/sign")
            ->assertOk()
            ->assertJsonPath('data.status', 'signed');

        $signedInvoice = $invoice->fresh();
        $this->assertSame('SIGNED-IRN-001', $signedInvoice->irn);
        $this->assertNotNull($signedInvoice->seller_snapshot);

        $this->actingAs($this->user)
            ->postJson("/api/v1/invoices/{$invoice->uuid}/transmit")
            ->assertOk()
            ->assertJsonPath('data.status', 'transmitted');

        $this->assertDatabaseHas('nrs_submissions', [
            'invoice_id' => $invoice->id,
            'action' => 'validate',
            'status' => 'success',
        ]);
        $this->assertDatabaseHas('nrs_submissions', [
            'invoice_id' => $invoice->id,
            'action' => 'sign',
            'status' => 'success',
        ]);
        $this->assertDatabaseHas('nrs_submissions', [
            'invoice_id' => $invoice->id,
            'action' => 'transmit',
            'status' => 'success',
        ]);
    }

    public function test_failed_transmission_can_be_retried_without_changing_signed_snapshot(): void
    {
        $this->actingAs($this->user)
            ->postJson('/api/v1/invoices', $this->validInvoicePayload())
            ->assertStatus(201);

        Http::fake([
            '*/api/v1/invoice/validate' => Http::response(['message' => 'validated'], 200),
            '*/api/v1/invoice/sign' => Http::response(['irn' => 'SIGNED-IRN-RETRY'], 200),
            '*/api/v1/invoice/transmit/*' => Http::sequence()
                ->push(['message' => 'service unavailable'], 503)
                ->push(['message' => 'transmitted'], 200),
        ]);

        $invoice = Invoice::firstOrFail();

        $this->actingAs($this->user)->postJson("/api/v1/invoices/{$invoice->uuid}/validate")->assertOk();
        $this->actingAs($this->user)->postJson("/api/v1/invoices/{$invoice->uuid}/sign")->assertOk();

        $signedInvoice = $invoice->fresh();
        $buyerSnapshot = $signedInvoice->buyer_snapshot;
        $lineSnapshot = $signedInvoice->line_snapshot;

        $this->customer->update(['name' => 'Changed After Signing']);
        $signedInvoice->lines()->first()->update(['item_name' => 'Changed After Signing']);

        $this->actingAs($this->user)
            ->postJson("/api/v1/invoices/{$invoice->uuid}/transmit")
            ->assertStatus(503);

        $this->assertSame('transmit_failed', $invoice->fresh()->status->value);

        $this->actingAs($this->user)
            ->postJson("/api/v1/invoices/{$invoice->uuid}/transmit")
            ->assertOk()
            ->assertJsonPath('data.status', 'transmitted');

        $transmittedInvoice = $invoice->fresh();
        $this->assertSame($buyerSnapshot['name'], $transmittedInvoice->buyer_snapshot['name']);
        $this->assertSame($lineSnapshot[0]['item_name'], $transmittedInvoice->line_snapshot[0]['item_name']);

        $this->assertDatabaseHas('nrs_submissions', [
            'invoice_id' => $invoice->id,
            'action' => 'transmit',
            'status' => 'failed',
        ]);
        $this->assertDatabaseHas('nrs_submissions', [
            'invoice_id' => $invoice->id,
            'action' => 'transmit',
            'status' => 'success',
        ]);
    }

    private function validInvoicePayload(array $overrides = []): array
    {
        return array_replace_recursive([
            'customer_id' => $this->customer->uuid,
            'invoice_number' => 'CLIENT-SUPPLIED',
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
        ], $overrides);
    }
}
