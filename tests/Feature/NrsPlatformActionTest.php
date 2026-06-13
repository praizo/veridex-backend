<?php

namespace Tests\Feature;

use App\Enums\InvoiceStatus;
use App\Models\Customer;
use App\Models\Invoice;
use App\Models\NrsApiLog;
use App\Models\NrsSubmission;
use App\Models\Organization;
use App\Models\User;
use App\Services\InvoiceStateService;
use App\Services\Nrs\NrsInvoiceService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class NrsPlatformActionTest extends TestCase
{
    use RefreshDatabase;

    protected User $user;

    protected Organization $organization;

    protected Customer $customer;

    protected function setUp(): void
    {
        parent::setUp();

        config([
            'nrs.base_url' => 'https://nrs.test',
            'nrs.api_key' => 'test-api-key',
            'nrs.api_secret' => 'test-api-secret',
        ]);

        $this->organization = Organization::create([
            'name' => 'Fixture Supplier Ltd',
            'slug' => 'fixture-supplier',
            'tin' => '12345678-0001',
            'email' => 'supplier@example.test',
            'telephone' => '08012345678',
            'nrs_business_id' => '0d70f6d2-ac1a-4261-b778-2825859d76c8',
        ]);

        $this->user = User::factory()->create([
            'current_organization_id' => $this->organization->id,
            'onboarding_completed_at' => now(),
        ]);

        $this->user->organizations()->attach($this->organization->id, ['role' => 'admin']);

        $this->customer = Customer::create([
            'organization_id' => $this->organization->id,
            'first_name' => 'Fixture Buyer Plc', 'last_name' => 'Last',
            'type' => 'business',
            'tin' => '87654321-0001',
            'email' => 'buyer@example.test',
            'telephone' => '08087654321',
        ]);
    }

    public function test_validate_invoice_posts_expected_payload_and_stores_redacted_metadata(): void
    {
        Http::fake([
            'https://nrs.test/api/v1/invoice/validate' => Http::response($this->fixture('invoice-validation-response'), 200),
        ]);

        $invoice = $this->draftInvoice();

        $this->actingAs($this->user)
            ->postJson("/api/v1/invoices/{$invoice->uuid}/validate")
            ->assertOk()
            ->assertJsonPath('data.status', 'validated');

        Http::assertSent(function ($request) {
            $payload = $request->data();

            return $request->method() === 'POST'
                && $request->url() === 'https://nrs.test/api/v1/invoice/validate'
                && $request->hasHeader('x-api-key', 'test-api-key')
                && $request->hasHeader('x-api-secret', 'test-api-secret')
                && $request->hasHeader('Accept', 'application/json')
                && $request->hasHeader('Content-Type', 'application/json')
                && $request->hasHeader('X-Idempotency-Key')
                && isset($payload['business_id'], $payload['invoice_line'][0]['item']['name'])
                && $payload['accounting_supplier_party']['tin'] === '12345678-0001';
        });

        $submission = NrsSubmission::where('invoice_id', $invoice->id)
            ->where('action', 'validate')
            ->firstOrFail();

        $this->assertSame('success', $submission->status);
        $this->assertSame('[REDACTED]', $submission->request_payload['business_id']);
        $this->assertSame('[REDACTED]', $submission->request_payload['accounting_supplier_party']['tin']);
        $this->assertSame('[REDACTED]', $submission->request_payload['accounting_supplier_party']['email']);

        $log = NrsApiLog::where('endpoint', 'api/v1/invoice/validate')->firstOrFail();
        $this->assertSame('POST', $log->method);
        $this->assertSame('[REDACTED]', $log->request_payload['business_id']);
    }

    public function test_mixed_goods_and_service_invoice_lines_map_to_expected_nrs_classification_fields(): void
    {
        Http::fake([
            'https://nrs.test/api/v1/invoice/validate' => Http::response($this->fixture('invoice-validation-response'), 200),
        ]);

        $invoice = $this->draftInvoice();
        $invoice->lines()->create([
            'line_id' => '2',
            'item_type' => 'service',
            'item_name' => 'Rice Growing Advisory',
            'item_description' => 'Agricultural advisory service',
            'isic_code' => '0112',
            'service_category' => 'Growing of rice',
            'invoiced_quantity' => 1,
            'unit_code' => 'EA',
            'price_amount' => 2000,
            'line_extension_amount' => 2000,
            'tax_category_id' => 'STANDARD_VAT',
            'tax_percent' => 7.5,
        ]);

        $this->actingAs($this->user)
            ->postJson("/api/v1/invoices/{$invoice->uuid}/validate")
            ->assertOk();

        Http::assertSent(function ($request) {
            $lines = $request->data()['invoice_line'] ?? [];

            return $request->method() === 'POST'
                && $request->url() === 'https://nrs.test/api/v1/invoice/validate'
                && isset($lines[0]['hsn_code'], $lines[0]['product_category'])
                && ! isset($lines[0]['isic_code'], $lines[0]['service_category'])
                && isset($lines[1]['isic_code'], $lines[1]['service_category'])
                && ! isset($lines[1]['hsn_code'], $lines[1]['product_category'])
                && $lines[1]['isic_code'] === '0112'
                && $lines[1]['service_category'] === 'Growing of rice';
        });
    }

    public function test_sign_invoice_auto_transmits_and_maps_responses(): void
    {
        Http::fake([
            'https://nrs.test/api/v1/invoice/sign' => Http::response($this->fixture('invoice-signing-response', [
                '{{SIGNED_IRN}}' => 'SIGNED-IRN-FIXTURE',
            ]), 200),
            'https://nrs.test/api/v1/invoice/transmit/SIGNED-IRN-FIXTURE' => Http::response($this->fixture('invoice-transmit-response', [
                '{{SIGNED_IRN}}' => 'SIGNED-IRN-FIXTURE',
            ]), 200),
        ]);

        $invoice = $this->validatedInvoice();

        $this->actingAs($this->user)
            ->postJson("/api/v1/invoices/{$invoice->uuid}/sign")
            ->assertOk()
            ->assertJsonPath('data.status', 'transmitted')
            ->assertJsonPath('data.irn', 'SIGNED-IRN-FIXTURE');

        Http::assertSent(function ($request) {
            return $request->method() === 'POST'
                && $request->url() === 'https://nrs.test/api/v1/invoice/sign'
                && $request->hasHeader('X-Idempotency-Key')
                && isset($request->data()['invoice_line'][0]['hsn_code']);
        });

        Http::assertSent(function ($request) {
            return $request->method() === 'POST'
                && $request->url() === 'https://nrs.test/api/v1/invoice/transmit/SIGNED-IRN-FIXTURE'
                && $request->data() === [];
        });

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

    public function test_transmit_failure_after_signing_is_retryable_without_resigning(): void
    {
        Http::fake([
            'https://nrs.test/api/v1/invoice/sign' => Http::response($this->fixture('invoice-signing-response', [
                '{{SIGNED_IRN}}' => 'SIGNED-IRN-RETRY-FIXTURE',
            ]), 200),
            'https://nrs.test/api/v1/invoice/transmit/SIGNED-IRN-RETRY-FIXTURE' => Http::sequence()
                ->push(['message' => 'temporary transmit failure'], 503)
                ->push($this->fixture('invoice-transmit-response', [
                    '{{SIGNED_IRN}}' => 'SIGNED-IRN-RETRY-FIXTURE',
                ]), 200),
        ]);

        $invoice = $this->validatedInvoice();

        $this->actingAs($this->user)
            ->postJson("/api/v1/invoices/{$invoice->uuid}/sign")
            ->assertStatus(207)
            ->assertJsonPath('transmit_failed', true)
            ->assertJsonPath('data.status', 'transmit_failed');

        $this->assertSame('transmit_failed', $invoice->fresh()->status->value);
        $this->assertNotNull($invoice->fresh()->seller_snapshot);

        $this->actingAs($this->user)
            ->postJson("/api/v1/invoices/{$invoice->uuid}/transmit")
            ->assertOk()
            ->assertJsonPath('data.status', 'transmitted');

        $this->assertSame(1, NrsSubmission::where('invoice_id', $invoice->id)->where('action', 'sign')->count());
        $this->assertSame(2, NrsSubmission::where('invoice_id', $invoice->id)->where('action', 'transmit')->count());
    }

    public function test_update_payment_uses_patch_endpoint_and_redacts_metadata(): void
    {
        Http::fake([
            'https://nrs.test/api/v1/invoice/update/SIGNED-IRN-PAYMENT' => Http::response($this->fixture('payment-update-response', [
                '{{SIGNED_IRN}}' => 'SIGNED-IRN-PAYMENT',
            ]), 200),
        ]);

        $invoice = $this->transmittedInvoice('SIGNED-IRN-PAYMENT');

        $this->actingAs($this->user)
            ->patchJson("/api/v1/invoices/{$invoice->uuid}/payment", [
                'payment_status' => 'PAID',
                'reference' => 'PAY-REF-001',
            ])
            ->assertOk()
            ->assertJsonPath('status', 'PAID');

        Http::assertSent(function ($request) {
            return $request->method() === 'PATCH'
                && $request->url() === 'https://nrs.test/api/v1/invoice/update/SIGNED-IRN-PAYMENT'
                && $request->hasHeader('X-Idempotency-Key')
                && $request->data()['payment_status'] === 'PAID'
                && $request->data()['reference'] === 'PAY-REF-001';
        });

        $this->assertDatabaseHas('nrs_submissions', [
            'invoice_id' => $invoice->id,
            'action' => 'update_payment',
            'status' => 'success',
        ]);
    }

    public function test_download_official_artifacts_stores_paths_hashes_and_uses_expected_headers(): void
    {
        Storage::fake('local');

        $pdf = $this->fixtureText('official-artifact-download-response.pdf');
        $xml = $this->fixtureText('official-artifact-download-response.xml', [
            '{{SIGNED_IRN}}' => 'SIGNED-IRN-ARTIFACT',
        ]);

        Http::fake([
            'https://nrs.test/api/v1/invoice/download/SIGNED-IRN-ARTIFACT' => Http::sequence()
                ->push($pdf, 200, ['Content-Type' => 'application/pdf'])
                ->push($xml, 200, ['Content-Type' => 'application/xml']),
        ]);

        $invoice = $this->transmittedInvoice('SIGNED-IRN-ARTIFACT');
        $artifact = app(NrsInvoiceService::class)->downloadOfficialArtifacts($invoice);
        $invoice = $invoice->fresh();

        $this->assertSame(hash('sha256', $pdf), $artifact['hash']);
        $this->assertSame(hash('sha256', $pdf), $invoice->official_pdf_hash);
        $this->assertSame(hash('sha256', $xml), $invoice->official_xml_hash);
        Storage::disk('local')->assertExists($invoice->official_pdf_path);
        Storage::disk('local')->assertExists($invoice->official_xml_path);

        Http::assertSent(function ($request) {
            return $request->method() === 'GET'
                && $request->url() === 'https://nrs.test/api/v1/invoice/download/SIGNED-IRN-ARTIFACT'
                && $request->hasHeader('Accept', 'application/json');
        });

        Http::assertSentCount(2);
    }

    public function test_deferred_confirmation_and_lookup_routes_are_not_active(): void
    {
        $invoice = $this->transmittedInvoice('SIGNED-IRN-NO-CONFIRM');

        $this->actingAs($this->user)
            ->postJson("/api/v1/invoices/{$invoice->uuid}/confirm")
            ->assertNotFound();

        $this->actingAs($this->user)
            ->getJson("/api/v1/invoices/{$invoice->uuid}/lookup")
            ->assertNotFound();
    }

    private function draftInvoice(): Invoice
    {
        $invoice = Invoice::create([
            'organization_id' => $this->organization->id,
            'customer_id' => $this->customer->id,
            'invoice_number' => 'INV-NRS-001',
            'status' => 'draft',
            'payment_status' => 'PENDING',
            'invoice_type_code' => '380',
            'invoice_kind' => 'B2B',
            'issue_date' => now(),
            'document_currency_code' => 'NGN',
            'irn' => 'DRAFT-IRN-FIXTURE',
            'line_extension_amount' => 1000,
            'tax_exclusive_amount' => 1000,
            'tax_inclusive_amount' => 1075,
            'payable_amount' => 1075,
        ]);

        $invoice->lines()->create([
            'line_id' => '1',
            'item_type' => 'goods',
            'item_name' => 'Fixture Service',
            'item_description' => 'Fixture service description',
            'hs_code' => '998314',
            'item_category' => 'Accounting services',
            'invoiced_quantity' => 1,
            'unit_code' => 'EA',
            'price_amount' => 1000,
            'line_extension_amount' => 1000,
            'tax_category_id' => 'STANDARD_VAT',
            'tax_percent' => 7.5,
        ]);

        $invoice->taxTotals()->create([
            'tax_amount' => 75,
            'taxable_amount' => 1000,
            'tax_category_id' => 'STANDARD_VAT',
            'tax_percent' => 7.5,
            'tax_scheme_id' => 'VAT',
        ]);

        return $invoice->fresh(['organization', 'customer', 'lines', 'taxTotals']);
    }

    private function validatedInvoice(): Invoice
    {
        $invoice = $this->draftInvoice();
        $stateService = app(InvoiceStateService::class);
        $stateService->transition($invoice, InvoiceStatus::PENDING_VALIDATION, $this->user, 'fixture validation');
        $stateService->transition($invoice->fresh(), InvoiceStatus::VALIDATED, $this->user, 'fixture validation');

        return $invoice->fresh(['organization', 'customer', 'lines', 'taxTotals']);
    }

    private function transmittedInvoice(string $irn): Invoice
    {
        $invoice = $this->draftInvoice();
        $stateService = app(InvoiceStateService::class);
        $stateService->transition($invoice, InvoiceStatus::PENDING_VALIDATION, $this->user, 'fixture validation');
        $stateService->transition($invoice->fresh(), InvoiceStatus::VALIDATED, $this->user, 'fixture validation');
        $stateService->transition($invoice->fresh(), InvoiceStatus::PENDING_SIGNING, $this->user, 'fixture signing');
        $invoice->fresh()->update(['irn' => $irn]);
        $stateService->transition($invoice->fresh(), InvoiceStatus::SIGNED, $this->user, 'fixture signing');
        $stateService->transition($invoice->fresh(), InvoiceStatus::PENDING_TRANSMIT, $this->user, 'fixture transmit');
        $stateService->transition($invoice->fresh(), InvoiceStatus::TRANSMITTED, $this->user, 'fixture transmit');

        return $invoice->fresh(['organization', 'customer', 'lines', 'taxTotals']);
    }

    private function fixture(string $name, array $replacements = []): array
    {
        return json_decode($this->fixtureText($name, $replacements), true, flags: JSON_THROW_ON_ERROR);
    }

    private function fixtureText(string $name, array $replacements = []): string
    {
        $path = base_path("tests/Fixtures/nrs/{$name}");
        if (! file_exists($path)) {
            $path = base_path("tests/Fixtures/nrs/{$name}.json");
        }
        if (! file_exists($path)) {
            $path = base_path("tests/Fixtures/nrs/{$name}.txt");
        }

        $content = file_get_contents($path);

        return strtr($content, array_merge([
            '{{TEST_IRN}}' => 'DRAFT-IRN-FIXTURE',
            '{{SIGNED_IRN}}' => 'SIGNED-IRN-FIXTURE',
            '{{TEST_BUSINESS_ID}}' => '0d70f6d2-ac1a-4261-b778-2825859d76c8',
            '{{SUPPLIER_NAME}}' => 'Fixture Supplier Ltd',
            '{{SUPPLIER_TIN}}' => '12345678-0001',
            '{{SUPPLIER_EMAIL}}' => 'supplier@example.test',
            '{{SUPPLIER_TELEPHONE}}' => '+2348012345678',
            '{{CUSTOMER_NAME}}' => 'Fixture Buyer Plc',
            '{{CUSTOMER_TIN}}' => '87654321-0001',
            '{{CUSTOMER_EMAIL}}' => 'buyer@example.test',
            '{{CUSTOMER_TELEPHONE}}' => '+2348087654321',
            '{{HS_CODE}}' => '998314',
            '{{PRODUCT_CATEGORY}}' => 'Accounting services',
            '{{ITEM_NAME}}' => 'Fixture Service',
            '{{ITEM_DESCRIPTION}}' => 'Fixture service description',
            '{{PAYMENT_REFERENCE}}' => 'PAY-REF-001',
        ], $replacements));
    }
}
