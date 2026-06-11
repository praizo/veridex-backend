<?php

namespace Tests\Feature;

use App\Models\Customer;
use App\Models\Invoice;
use App\Models\Organization;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ReportAnalyticsTest extends TestCase
{
    use RefreshDatabase;

    protected User $user;

    protected Organization $organization;

    protected Customer $businessCustomer;

    protected Customer $individualCustomer;

    protected Customer $governmentCustomer;

    protected function setUp(): void
    {
        parent::setUp();

        $this->organization = Organization::create([
            'name' => 'Report Org',
            'slug' => 'report-org',
            'tin' => '12345678-0001',
            'email' => 'reports@test.com',
            'nrs_business_id' => '0d70f6d2-ac1a-4261-b778-2825859d76c8',
        ]);

        $this->user = User::factory()->create([
            'current_organization_id' => $this->organization->id,
            'onboarding_completed_at' => now(),
        ]);

        $this->user->organizations()->attach($this->organization->id, ['role' => 'admin']);

        $this->businessCustomer = $this->customer('Acme Business Ltd', 'business');
        $this->individualCustomer = $this->customer('Ada Individual', 'individual');
        $this->governmentCustomer = $this->customer('Ministry Customer', 'government');
    }

    public function test_invoice_analytics_defaults_to_b2b_segment(): void
    {
        $this->invoice('BIZ-001', $this->businessCustomer, 'signed', 'PENDING', 'STANDARD_VAT', '2026-06-01', 1000);
        $this->invoice('B2C-001', $this->individualCustomer, 'signed', 'PENDING', 'STANDARD_VAT', '2026-06-01', 9000);

        $response = $this->actingAs($this->user)->getJson('/api/v1/reports/invoices/summary');

        $response->assertOk()
            ->assertJsonPath('summary.count', 1)
            ->assertJsonPath('summary.amount', 1075);
    }

    public function test_customer_type_filter_selects_requested_segment(): void
    {
        $this->invoice('BIZ-001', $this->businessCustomer, 'signed', 'PENDING', 'STANDARD_VAT', '2026-06-01', 1000);
        $this->invoice('B2C-001', $this->individualCustomer, 'signed', 'PENDING', 'STANDARD_VAT', '2026-06-01', 2000);
        $this->invoice('B2G-001', $this->governmentCustomer, 'signed', 'PENDING', 'STANDARD_VAT', '2026-06-01', 3000);

        $this->actingAs($this->user)
            ->getJson('/api/v1/reports/invoices/summary?customer_type=individual')
            ->assertOk()
            ->assertJsonPath('summary.count', 1)
            ->assertJsonPath('summary.amount', 2150);

        $this->actingAs($this->user)
            ->getJson('/api/v1/reports/invoices/summary?customer_type=all')
            ->assertOk()
            ->assertJsonPath('summary.count', 3);
    }

    public function test_status_filter_is_lifecycle_aware(): void
    {
        $this->invoice('SIGNED-001', $this->businessCustomer, 'signed', 'PENDING', 'STANDARD_VAT', '2026-06-01', 1000);
        $this->invoice('FAILED-001', $this->businessCustomer, 'transmit_failed', 'PENDING', 'STANDARD_VAT', '2026-06-01', 2000);
        $this->invoice('SENT-001', $this->businessCustomer, 'transmitted', 'PENDING', 'STANDARD_VAT', '2026-06-01', 3000);
        $this->invoice('DRAFT-001', $this->businessCustomer, 'draft', 'PENDING', 'STANDARD_VAT', '2026-06-01', 4000);

        $response = $this->actingAs($this->user)
            ->getJson('/api/v1/reports/invoices/summary?status=signed');

        $response->assertOk()
            ->assertJsonPath('summary.count', 3)
            ->assertJsonPath('summary.amount', 6450);

        $statuses = collect($response->json('status_summary'))->pluck('status')->sort()->values()->all();
        $this->assertSame(['signed', 'transmit_failed', 'transmitted'], $statuses);
    }

    public function test_payment_status_filter(): void
    {
        $this->invoice('PENDING-001', $this->businessCustomer, 'signed', 'PENDING', 'STANDARD_VAT', '2026-06-01', 1000);
        $this->invoice('PAID-001', $this->businessCustomer, 'signed', 'PAID', 'STANDARD_VAT', '2026-06-01', 2000);

        $this->actingAs($this->user)
            ->getJson('/api/v1/reports/invoices/summary?payment_status=PAID')
            ->assertOk()
            ->assertJsonPath('summary.count', 1)
            ->assertJsonPath('payment_summary.0.payment_status', 'PAID');
    }

    public function test_tax_category_filter(): void
    {
        $this->invoice('VAT-001', $this->businessCustomer, 'signed', 'PENDING', 'STANDARD_VAT', '2026-06-01', 1000);
        $this->invoice('ZERO-001', $this->businessCustomer, 'signed', 'PENDING', 'ZERO_VAT', '2026-06-01', 2000, 0);

        $this->actingAs($this->user)
            ->getJson('/api/v1/reports/invoices/summary?tax_category_id=ZERO_VAT')
            ->assertOk()
            ->assertJsonPath('summary.count', 1)
            ->assertJsonPath('summary.amount', 2000);
    }

    public function test_date_range_filter(): void
    {
        $this->invoice('OLD-001', $this->businessCustomer, 'signed', 'PENDING', 'STANDARD_VAT', '2026-05-31', 1000);
        $this->invoice('NEW-001', $this->businessCustomer, 'signed', 'PENDING', 'STANDARD_VAT', '2026-06-10', 2000);

        $this->actingAs($this->user)
            ->getJson('/api/v1/reports/invoices/summary?date_from=2026-06-01&date_to=2026-06-30')
            ->assertOk()
            ->assertJsonPath('summary.count', 1)
            ->assertJsonPath('summary.amount', 2150);
    }

    public function test_csv_export_uses_same_filters_as_dashboard(): void
    {
        $this->invoice('MATCH-001', $this->businessCustomer, 'transmit_failed', 'PAID', 'STANDARD_VAT', '2026-06-10', 1000);
        $this->invoice('MATCH-002', $this->businessCustomer, 'transmitted', 'PAID', 'STANDARD_VAT', '2026-06-11', 2000);
        $this->invoice('MISS-001', $this->businessCustomer, 'draft', 'PAID', 'STANDARD_VAT', '2026-06-11', 3000);
        $this->invoice('MISS-002', $this->individualCustomer, 'transmitted', 'PAID', 'STANDARD_VAT', '2026-06-11', 4000);

        $query = 'status=signed&payment_status=PAID&date_from=2026-06-01&date_to=2026-06-30';

        $summary = $this->actingAs($this->user)
            ->getJson("/api/v1/reports/invoices/summary?{$query}")
            ->assertOk()
            ->json('summary');

        $csv = $this->actingAs($this->user)
            ->get("/api/v1/reports/invoices/csv?{$query}")
            ->assertOk()
            ->streamedContent();

        $this->assertSame(2, $summary['count']);
        $this->assertStringContainsString('MATCH-001', $csv);
        $this->assertStringContainsString('MATCH-002', $csv);
        $this->assertStringNotContainsString('MISS-001', $csv);
        $this->assertStringNotContainsString('MISS-002', $csv);
        $this->assertStringContainsString('transmit_failed', $csv);
        $this->assertStringContainsString('transmitted', $csv);
        $this->assertStringContainsString('PAID', $csv);
    }

    public function test_enum_statuses_serialize_as_strings_in_resources_and_exports(): void
    {
        $invoice = $this->invoice('ENUM-001', $this->businessCustomer, 'signed', 'PAID', 'STANDARD_VAT', '2026-06-10', 1000);

        $this->actingAs($this->user)
            ->getJson("/api/v1/invoices/{$invoice->uuid}")
            ->assertOk()
            ->assertJsonPath('data.status', 'signed')
            ->assertJsonPath('data.payment_status', 'PAID');

        $csv = $this->actingAs($this->user)
            ->get('/api/v1/reports/invoices/csv?status=signed&payment_status=PAID')
            ->assertOk()
            ->streamedContent();

        $this->assertStringContainsString('signed,PAID', $csv);
    }

    private function customer(string $name, string $type): Customer
    {
        return Customer::create([
            'organization_id' => $this->organization->id,
            'name' => $name,
            'type' => $type,
            'tin' => fake()->numerify('########-####'),
            'email' => fake()->safeEmail(),
        ]);
    }

    private function invoice(
        string $number,
        Customer $customer,
        string $status,
        string $paymentStatus,
        string $taxCategory,
        string $issueDate,
        float $taxExclusive,
        float $taxPercent = 7.5
    ): Invoice {
        $taxAmount = round($taxExclusive * ($taxPercent / 100), 2);
        $payable = round($taxExclusive + $taxAmount, 2);

        $invoice = Invoice::create([
            'organization_id' => $this->organization->id,
            'customer_id' => $customer->id,
            'invoice_number' => $number,
            'status' => $status,
            'payment_status' => $paymentStatus,
            'issue_date' => $issueDate,
            'due_date' => '2026-06-30',
            'document_currency_code' => 'NGN',
            'irn' => "{$number}-IRN",
            'line_extension_amount' => $taxExclusive,
            'tax_exclusive_amount' => $taxExclusive,
            'tax_inclusive_amount' => $payable,
            'payable_amount' => $payable,
        ]);

        $invoice->taxTotals()->create([
            'tax_amount' => $taxAmount,
            'taxable_amount' => $taxExclusive,
            'tax_category_id' => $taxCategory,
            'tax_percent' => $taxPercent,
            'tax_scheme_id' => 'VAT',
        ]);

        return $invoice;
    }
}

