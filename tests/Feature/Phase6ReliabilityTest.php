<?php

namespace Tests\Feature;

use App\Enums\InvoiceStatus;
use App\Exceptions\NrsApiException;
use App\Exceptions\NrsConnectionException;
use App\Jobs\ProcessNrsWebhookJob;
use App\Models\Customer;
use App\Models\Invoice;
use App\Models\NrsSubmission;
use App\Models\Organization;
use App\Models\User;
use App\Services\Invoice\InvoiceStateService;
use App\Services\Nrs\NrsClient;
use App\Services\Operations\OperationalMetricService;
use App\Support\SensitiveDataRedactor;
use GuzzleHttp\Exception\ConnectException;
use GuzzleHttp\Psr7\Request;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class Phase6ReliabilityTest extends TestCase
{
    use RefreshDatabase;

    private Organization $organization;

    private User $user;

    private Customer $customer;

    protected function setUp(): void
    {
        parent::setUp();

        $this->organization = Organization::create([
            'name' => 'Reliability Org',
            'slug' => 'reliability-org',
            'tin' => '12345678-0001',
            'email' => 'ops@example.com',
            'nrs_business_id' => '0d70f6d2-ac1a-4261-b778-2825859d76c8',
        ]);

        $this->user = User::factory()->create([
            'current_organization_id' => $this->organization->id,
            'onboarding_completed_at' => now(),
        ]);
        $this->user->organizations()->attach($this->organization->id, ['role' => 'admin']);

        $this->customer = Customer::create([
            'organization_id' => $this->organization->id,
            'first_name' => 'Test Customer', 'last_name' => 'Last',
            'type' => 'individual',
            'tin' => '87654321-0001',
            'email' => 'cust@test.com',
        ]);
    }

    public function test_duplicate_invoice_create_request_returns_original_result(): void
    {
        $payload = $this->validInvoicePayload();

        $first = $this->actingAs($this->user)
            ->withHeader('Idempotency-Key', 'create-invoice-once')
            ->postJson('/api/v1/invoices', $payload);

        $second = $this->actingAs($this->user)
            ->withHeader('Idempotency-Key', 'create-invoice-once')
            ->postJson('/api/v1/invoices', $payload);

        $first->assertCreated();
        $second->assertCreated()
            ->assertHeader('X-Idempotent-Replay', 'true')
            ->assertJsonPath('data.invoice_number', $first->json('data.invoice_number'));

        $this->assertSame(1, Invoice::count());
    }

    public function test_duplicate_webhook_does_not_create_duplicate_submission_or_retry_noise(): void
    {
        $invoice = Invoice::create([
            'organization_id' => $this->organization->id,
            'customer_id' => $this->customer->id,
            'invoice_number' => 'INV-2026-000001',
            'status' => InvoiceStatus::SIGNED,
            'payment_status' => 'PENDING',
            'issue_date' => now(),
            'irn' => 'TEST-IRN-12345',
            'line_extension_amount' => 1000,
            'tax_exclusive_amount' => 1000,
            'tax_inclusive_amount' => 1075,
            'payable_amount' => 1075,
        ]);

        $payload = ['message' => 'TRANSMITTING', 'irn' => $invoice->irn];
        $job = new ProcessNrsWebhookJob($payload);
        $job->handle(app(InvoiceStateService::class), app(SensitiveDataRedactor::class), app(OperationalMetricService::class));

        $duplicate = new ProcessNrsWebhookJob($payload);
        $duplicate->handle(app(InvoiceStateService::class), app(SensitiveDataRedactor::class), app(OperationalMetricService::class));

        $this->assertSame(1, NrsSubmission::where('idempotency_key', "webhook_TRANSMITTING_{$invoice->irn}")->count());
        $this->assertSame(InvoiceStatus::PENDING_TRANSMIT, $invoice->fresh()->status);
    }

    public function test_nrs_outage_opens_circuit_breaker_and_blocks_followup_request(): void
    {
        config([
            'nrs.base_url' => 'https://nrs.example.test',
            'nrs.api_key' => 'api-key',
            'nrs.api_secret' => 'api-secret',
        ]);

        Http::fake([
            'https://nrs.example.test/*' => Http::response(['message' => 'NRS down'], 503),
        ]);

        $client = app(NrsClient::class);

        for ($i = 0; $i < 5; $i++) {
            try {
                $client->post('api/v1/test', ['attempt' => $i]);
            } catch (NrsApiException) {
                // Expected while the breaker is warming up.
            }
        }

        $this->expectException(NrsConnectionException::class);
        $client->post('api/v1/test', ['attempt' => 6]);
    }

    public function test_failed_jobs_are_observable_by_queue_health_command(): void
    {
        DB::table('failed_jobs')->insert([
            'uuid' => 'failed-job-1',
            'connection' => 'database',
            'queue' => 'default',
            'payload' => '{}',
            'exception' => 'Test failure',
            'failed_at' => now(),
        ]);

        Artisan::call('ops:queue-health');

        $this->assertStringContainsString('1 failed', Artisan::output());
    }

    public function test_guzzle_connect_exception_is_wrapped_and_sanitized(): void
    {
        config([
            'nrs.base_url' => 'https://nrs.example.test',
            'nrs.api_key' => 'api-key',
            'nrs.api_secret' => 'api-secret',
        ]);

        $request = new Request('GET', 'https://nrs.example.test/api/v1/invoice/transmit/self-health-check');
        Http::fake([
            'https://nrs.example.test/*' => function () use ($request) {
                throw new ConnectException(
                    'cURL error 6: Could not resolve host: eivc-k6z6d.ondigitalocean.app',
                    $request
                );
            },
        ]);

        // 1. Verify that NrsClient request maps this ConnectException to NrsConnectionException with clean message
        $client = app(NrsClient::class);
        try {
            $client->get('api/v1/invoice/transmit/self-health-check');
            $this->fail('Expected NrsConnectionException was not thrown.');
        } catch (NrsConnectionException $e) {
            $this->assertSame(
                'The official FIRS/NRS service is temporarily unreachable. Please check your internet connection or try again later.',
                $e->getMessage()
            );
        }

        // 2. Verify that dashboard health endpoint returns sanitized message
        $response = $this->actingAs($this->user)->getJson('/api/v1/dashboard/health');
        $response->assertOk()
            ->assertJson([
                'status' => 'offline',
                'error' => 'The official FIRS/NRS service is temporarily unreachable. Please check your internet connection or try again later.',
            ]);
    }

    private function validInvoicePayload(): array
    {
        return [
            'customer_id' => $this->customer->uuid,
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
    }
}
