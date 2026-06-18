<?php

namespace Tests\Feature;

use App\Enums\InvoiceStatus;
use App\Models\Customer;
use App\Models\Invoice;
use App\Models\Organization;
use App\Models\User;
use App\Services\Invoice\InvoiceStateService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Queue;
use Tests\TestCase;

class BackendRemediationTest extends TestCase
{
    use RefreshDatabase;

    public function test_invoice_mass_assignment_rejects_system_owned_fields(): void
    {
        $organization = Organization::factory()->create([
            'tin' => '12345678',
            'service_id' => 'ABCDEFGH',
        ]);
        $customer = Customer::factory()->create(['organization_id' => $organization->id]);

        $invoice = Invoice::create([
            'organization_id' => $organization->id,
            'customer_id' => $customer->id,
            'invoice_number' => 'INV-SEC-001',
            'status' => InvoiceStatus::CONFIRMED->value,
            'irn' => 'MALICIOUS-IRN',
            'seller_snapshot' => ['tampered' => true],
            'official_pdf_path' => 'tampered.pdf',
            'issue_date' => '2026-06-18',
            'invoice_type_code' => '380',
            'document_currency_code' => 'NGN',
            'payment_status' => 'PENDING',
            'line_extension_amount' => 1000,
            'tax_exclusive_amount' => 1000,
            'tax_inclusive_amount' => 1075,
            'allowance_total_amount' => 0,
            'charge_total_amount' => 0,
            'prepaid_amount' => 0,
            'payable_rounding_amount' => 0,
            'payable_amount' => 1075,
        ]);

        $invoice = $invoice->fresh();

        $this->assertSame(InvoiceStatus::DRAFT, $invoice->status);
        $this->assertNotSame('MALICIOUS-IRN', $invoice->irn);
        $this->assertNull($invoice->seller_snapshot);
        $this->assertNull($invoice->official_pdf_path);
    }

    public function test_organization_mass_assignment_rejects_platform_fields(): void
    {
        $organization = Organization::create([
            'name' => 'Tenant Org',
            'slug' => 'tenant-org',
            'tin' => '12345678',
            'email' => 'tenant@example.com',
            'country_code' => 'NG',
            'platform_status' => 'suspended',
            'onboarding_status' => 'onboarded',
            'verified_at' => now(),
            'suspended_at' => now(),
            'admin_notes' => 'tampered',
        ]);

        $organization = $organization->fresh();

        $this->assertSame('active', $organization->platform_status);
        $this->assertSame('pending', $organization->onboarding_status);
        $this->assertNull($organization->verified_at);
        $this->assertNull($organization->suspended_at);
        $this->assertNull($organization->admin_notes);
    }

    public function test_trusted_state_service_can_update_protected_invoice_status(): void
    {
        $organization = Organization::factory()->create();
        $customer = Customer::factory()->create(['organization_id' => $organization->id]);
        $user = $this->member($organization, 'admin');
        $invoice = Invoice::factory()->create([
            'organization_id' => $organization->id,
            'customer_id' => $customer->id,
            'created_by' => $user->id,
            'status' => InvoiceStatus::DRAFT,
        ]);

        app(InvoiceStateService::class)->transition(
            invoice: $invoice,
            toStatus: InvoiceStatus::PENDING_VALIDATION,
            user: $user,
            trigger: 'test'
        );

        $this->assertSame(InvoiceStatus::PENDING_VALIDATION, $invoice->fresh()->status);
    }

    public function test_invoice_policy_role_matrix(): void
    {
        $organization = Organization::factory()->create();
        $customer = Customer::factory()->create(['organization_id' => $organization->id]);
        $invoice = Invoice::factory()->create([
            'organization_id' => $organization->id,
            'customer_id' => $customer->id,
            'status' => InvoiceStatus::DRAFT,
        ]);

        $viewer = $this->member($organization, 'viewer');
        $accountant = $this->member($organization, 'accountant');
        $editor = $this->member($organization, 'editor');

        $this->assertTrue(Gate::forUser($viewer)->allows('view', $invoice));
        $this->assertFalse(Gate::forUser($viewer)->allows('manageLifecycle', $invoice));
        $this->assertFalse(Gate::forUser($viewer)->allows('update', $invoice));

        $this->assertTrue(Gate::forUser($accountant)->allows('manageLifecycle', $invoice));
        $this->assertFalse(Gate::forUser($accountant)->allows('update', $invoice));

        $this->assertTrue(Gate::forUser($editor)->allows('update', $invoice));
        $this->assertTrue(Gate::forUser($editor)->allows('manageLifecycle', $invoice));
    }

    public function test_organization_report_activity_policy_role_matrix(): void
    {
        $organization = Organization::factory()->create();
        $viewer = $this->member($organization, 'viewer');
        $accountant = $this->member($organization, 'accountant');
        $admin = $this->member($organization, 'admin');

        $this->assertFalse(Gate::forUser($viewer)->allows('viewReports', $organization));
        $this->assertFalse(Gate::forUser($viewer)->allows('viewActivityLogs', $organization));

        $this->assertTrue(Gate::forUser($accountant)->allows('viewReports', $organization));
        $this->assertTrue(Gate::forUser($accountant)->allows('viewActivityLogs', $organization));

        $this->assertTrue(Gate::forUser($admin)->allows('update', $organization));
    }

    public function test_nrs_webhook_signature_accepts_valid_headers(): void
    {
        Queue::fake();
        config([
            'services.nrs.webhook_verify_signature' => true,
            'services.nrs.webhook_api_key' => 'webhook-key',
            'services.nrs.webhook_secret' => 'webhook-secret',
        ]);

        $payload = ['message' => 'TRANSMITTING', 'irn' => 'IRN-123'];
        $timestamp = now()->toIso8601String();

        $this->postJson('/api/v1/invoice/webhook', $payload, $this->webhookHeaders($payload, $timestamp))
            ->assertOk()
            ->assertJsonPath('message', 'ACKNOWLEDGED');
    }

    public function test_nrs_webhook_signature_rejects_missing_invalid_and_stale_headers(): void
    {
        config([
            'services.nrs.webhook_verify_signature' => true,
            'services.nrs.webhook_api_key' => 'webhook-key',
            'services.nrs.webhook_secret' => 'webhook-secret',
            'services.nrs.webhook_timestamp_tolerance' => 300,
        ]);

        $payload = ['message' => 'TRANSMITTING', 'irn' => 'IRN-123'];
        $timestamp = now()->toIso8601String();

        $this->postJson('/api/v1/invoice/webhook', $payload)->assertUnauthorized();

        $this->postJson('/api/v1/invoice/webhook', $payload, [
            'X-API-Key' => 'webhook-key',
            'X-Timestamp' => $timestamp,
            'X-Signature' => 'invalid',
        ])->assertUnauthorized();

        $staleTimestamp = now()->subMinutes(10)->toIso8601String();
        $this->postJson('/api/v1/invoice/webhook', $payload, $this->webhookHeaders($payload, $staleTimestamp))
            ->assertUnauthorized();
    }

    public function test_nrs_webhook_signature_can_be_bypassed_for_local_testing(): void
    {
        Queue::fake();
        config(['services.nrs.webhook_verify_signature' => false]);

        $this->postJson('/api/v1/invoice/webhook', ['message' => 'TRANSMITTING', 'irn' => 'IRN-123'])
            ->assertOk()
            ->assertJsonPath('message', 'ACKNOWLEDGED');
    }

    private function member(Organization $organization, string $role): User
    {
        $user = User::factory()->create([
            'current_organization_id' => $organization->id,
            'onboarding_completed_at' => now(),
        ]);

        $user->organizations()->attach($organization->id, ['role' => $role]);

        return $user;
    }

    private function webhookHeaders(array $payload, string $timestamp): array
    {
        return [
            'X-API-Key' => 'webhook-key',
            'X-Timestamp' => $timestamp,
            'X-Signature' => hash_hmac('sha256', json_encode($payload).$timestamp, 'webhook-secret'),
        ];
    }
}
