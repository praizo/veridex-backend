<?php

namespace Tests\Feature;

use App\Enums\InvoiceStatus;
use App\Models\Customer;
use App\Models\Invoice;
use App\Models\Organization;
use App\Models\User;
use App\Services\InvoicePdfService;
use App\Services\InvoiceStateService;
use App\Services\Nrs\NrsArtifactService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class PdfArtifactQaTest extends TestCase
{
    use RefreshDatabase;

    protected User $user;

    protected Organization $organization;

    protected Customer $customer;

    protected function setUp(): void
    {
        parent::setUp();

        $this->organization = Organization::create([
            'name' => 'Original Supplier Ltd',
            'slug' => 'pdf-org',
            'tin' => '12345678-0001',
            'email' => 'supplier@test.com',
            'street_name' => '10 Supplier Road',
            'city_name' => 'Lagos',
            'country_code' => 'NG',
            'nrs_business_id' => '0d70f6d2-ac1a-4261-b778-2825859d76c8',
        ]);

        $this->user = User::factory()->create([
            'current_organization_id' => $this->organization->id,
            'onboarding_completed_at' => now(),
        ]);

        $this->user->organizations()->attach($this->organization->id, ['role' => 'admin']);

        $this->customer = Customer::create([
            'organization_id' => $this->organization->id,
            'name' => 'Original Buyer Plc',
            'type' => 'business',
            'tin' => '87654321-0001',
            'email' => 'buyer@test.com',
            'street_name' => '20 Buyer Avenue',
            'city_name' => 'Abuja',
            'country_code' => 'NG',
        ]);
    }

    public function test_signed_pdf_rendering_uses_immutable_snapshots(): void
    {
        $invoice = $this->signedInvoice();
        $lineSnapshot = $invoice->line_snapshot;
        $buyerSnapshot = $invoice->buyer_snapshot;
        $sellerSnapshot = $invoice->seller_snapshot;

        $this->organization->update(['name' => 'Changed Supplier Ltd']);
        $this->customer->update(['name' => 'Changed Buyer Plc']);
        $invoice->lines()->first()->update(['item_name' => 'Changed Line Item']);

        $pdfInvoice = app(InvoicePdfService::class)->applyImmutableSnapshots($invoice->fresh());

        $this->assertSame($sellerSnapshot['name'], $pdfInvoice->organization->name);
        $this->assertSame($buyerSnapshot['name'], $pdfInvoice->customer->name);
        $this->assertSame($lineSnapshot[0]['item_name'], $pdfInvoice->lines->first()->item_name);
    }

    public function test_downloaded_pdf_generates_hash_and_valid_pdf_response(): void
    {
        $invoice = $this->signedInvoice();

        $response = $this->actingAs($this->user)
            ->get("/api/v1/invoices/{$invoice->uuid}/download");

        $response->assertOk()
            ->assertHeader('Content-Type', 'application/pdf');

        $this->assertStringStartsWith('%PDF-', $response->getContent());
        $this->assertNotNull($invoice->fresh()->pdf_hash);
    }

    public function test_official_pdf_and_xml_artifact_paths_and_hashes_are_stored(): void
    {
        Storage::fake('local');

        $invoice = $this->signedInvoice();
        $pdf = "%PDF-1.4\n% official invoice\n";
        $xml = '<?xml version="1.0"?><Invoice><ID>TEST</ID></Invoice>';

        Http::fake([
            '*' => Http::sequence()
                ->push($pdf, 200, ['Content-Type' => 'application/pdf'])
                ->push($xml, 200, ['Content-Type' => 'application/xml']),
        ]);

        $artifact = app(NrsArtifactService::class)->downloadAndStorePdf($invoice);
        $invoice = $invoice->fresh();

        $this->assertSame(hash('sha256', $pdf), $artifact['hash']);
        $this->assertSame(hash('sha256', $pdf), $invoice->official_pdf_hash);
        $this->assertSame(hash('sha256', $pdf), $invoice->pdf_hash);
        $this->assertSame(hash('sha256', $xml), $invoice->official_xml_hash);
        $this->assertSame(hash('sha256', $xml), $invoice->xml_hash);

        Storage::disk('local')->assertExists($invoice->official_pdf_path);
        Storage::disk('local')->assertExists($invoice->official_xml_path);
    }

    private function signedInvoice(): Invoice
    {
        $invoice = Invoice::create([
            'organization_id' => $this->organization->id,
            'customer_id' => $this->customer->id,
            'invoice_number' => 'INV-PDF-001',
            'status' => 'draft',
            'payment_status' => 'PENDING',
            'issue_date' => now(),
            'due_date' => now()->addDays(14),
            'document_currency_code' => 'NGN',
            'irn' => 'PDF-TEST-IRN',
            'line_extension_amount' => 1000000,
            'tax_exclusive_amount' => 1000000,
            'tax_inclusive_amount' => 1075000,
            'payable_amount' => 1075000,
        ]);

        $invoice->lines()->create([
            'line_id' => '1',
            'item_name' => 'Enterprise implementation with a very long descriptive service name',
            'item_description' => 'Long implementation description for visual wrapping and PDF QA.',
            'hsn_code' => '998314',
            'product_category' => 'STANDARD_VAT',
            'invoiced_quantity' => 2,
            'price_amount' => 500000,
            'line_extension_amount' => 1000000,
            'tax_category_id' => 'STANDARD_VAT',
            'tax_percent' => 7.5,
        ]);

        $invoice->taxTotals()->create([
            'tax_amount' => 75000,
            'taxable_amount' => 1000000,
            'tax_category_id' => 'STANDARD_VAT',
            'tax_percent' => 7.5,
            'tax_scheme_id' => 'VAT',
        ]);

        $stateService = app(InvoiceStateService::class);
        $stateService->transition($invoice, InvoiceStatus::PENDING_VALIDATION, $this->user, 'test');
        $stateService->transition($invoice->fresh(), InvoiceStatus::VALIDATED, $this->user, 'test');
        $stateService->transition($invoice->fresh(), InvoiceStatus::PENDING_SIGNING, $this->user, 'test');
        $stateService->transition($invoice->fresh(), InvoiceStatus::SIGNED, $this->user, 'test');

        return $invoice->fresh(['organization', 'customer', 'lines', 'taxTotals']);
    }
}
