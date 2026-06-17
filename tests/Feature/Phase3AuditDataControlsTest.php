<?php

namespace Tests\Feature;

use App\Models\ActivityLog;
use App\Models\NrsApiLog;
use App\Models\Organization;
use App\Models\User;
use App\Services\ActivityLog\ActivityLogService;
use App\Services\Nrs\NrsClient;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class Phase3AuditDataControlsTest extends TestCase
{
    use RefreshDatabase;

    public function test_nrs_api_log_payloads_redact_sensitive_keys(): void
    {
        $organization = Organization::create([
            'name' => 'Audit Org',
            'slug' => 'audit-org',
            'tin' => '12345678-0001',
            'email' => 'audit@example.com',
        ]);

        $user = User::factory()->create([
            'current_organization_id' => $organization->id,
            'onboarding_completed_at' => now(),
        ]);
        $user->organizations()->attach($organization->id, ['role' => 'admin']);

        config([
            'nrs.base_url' => 'https://nrs.example.test',
            'nrs.api_key' => 'live-api-key',
            'nrs.api_secret' => 'live-api-secret',
        ]);

        Http::fake([
            'https://nrs.example.test/*' => Http::response([
                'access_token' => 'remote-token',
                'customer' => [
                    'postal_address' => [
                        'street_name' => '1 Secret Street',
                    ],
                ],
                'status' => 'ok',
            ]),
        ]);

        $this->actingAs($user);

        app(NrsClient::class)->post('api/v1/test', [
            'password' => 'secret-password',
            'accounting_customer_party' => [
                'email' => 'buyer@example.com',
                'postal_address' => [
                    'street_name' => '1 Secret Street',
                    'city_name' => 'Lagos',
                ],
            ],
            'invoice' => [
                'irn' => 'TEST-IRN',
            ],
        ]);

        $log = NrsApiLog::firstOrFail();

        $this->assertSame('[REDACTED]', $log->request_payload['password']);
        $this->assertSame('[REDACTED]', $log->request_payload['accounting_customer_party']['postal_address']);
        $this->assertSame('[REDACTED]', $log->response_body['access_token']);
        $this->assertSame('[REDACTED]', $log->response_body['customer']['postal_address']);
        $this->assertSame('ok', $log->response_body['status']);
    }

    public function test_activity_log_metadata_redacts_sensitive_keys(): void
    {
        $organization = Organization::create([
            'name' => 'Audit Org',
            'slug' => 'audit-org',
            'tin' => '12345678-0001',
            'email' => 'audit@example.com',
        ]);

        $user = User::factory()->create([
            'current_organization_id' => $organization->id,
            'onboarding_completed_at' => now(),
        ]);
        $user->organizations()->attach($organization->id, ['role' => 'admin']);

        app(ActivityLogService::class)->log($user, 'TEST_SECRET_METADATA', $organization, 'Testing redaction', [
            'raw_error' => [
                'x-api-secret' => 'secret',
                'public_message' => 'Validation failed',
            ],
            'otp_code' => '123456',
            'safe_context' => 'kept',
        ]);

        $log = ActivityLog::firstOrFail();

        $this->assertSame('[REDACTED]', $log->metadata['raw_error']);
        $this->assertSame('[REDACTED]', $log->metadata['otp_code']);
        $this->assertSame('kept', $log->metadata['safe_context']);
    }

    public function test_viewer_cannot_access_activity_logs(): void
    {
        $organization = Organization::create([
            'name' => 'Audit Org',
            'slug' => 'audit-org',
            'tin' => '12345678-0001',
            'email' => 'audit@example.com',
        ]);

        $viewer = User::factory()->create([
            'current_organization_id' => $organization->id,
            'onboarding_completed_at' => now(),
        ]);
        $viewer->organizations()->attach($organization->id, ['role' => 'viewer']);

        ActivityLog::create([
            'organization_id' => $organization->id,
            'user_id' => $viewer->id,
            'action' => 'TEST',
            'subject_type' => Organization::class,
            'subject_id' => $organization->id,
            'description' => 'Test log',
        ]);

        $this->actingAs($viewer)
            ->getJson('/api/v1/activity-logs')
            ->assertForbidden();
    }

    public function test_dev_only_nrs_raw_debug_export_writes_raw_payload_with_masked_headers(): void
    {
        Storage::fake('local');

        $organization = Organization::create([
            'name' => 'Audit Org',
            'slug' => 'audit-org',
            'tin' => '12345678-0001',
            'email' => 'audit@example.com',
        ]);

        $user = User::factory()->create([
            'current_organization_id' => $organization->id,
            'onboarding_completed_at' => now(),
        ]);
        $user->organizations()->attach($organization->id, ['role' => 'admin']);

        config([
            'audit.nrs_raw_debug_export' => true,
            'audit.nrs_raw_debug_disk' => 'local',
            'audit.nrs_raw_debug_path' => 'nrs-debug',
            'nrs.base_url' => 'https://nrs.example.test',
            'nrs.api_key' => 'live-api-key',
            'nrs.api_secret' => 'live-api-secret',
        ]);

        Http::fake([
            'https://nrs.example.test/*' => Http::response([
                'access_token' => 'raw-response-token',
                'status' => 'ok',
            ]),
        ]);

        $this->actingAs($user);

        app(NrsClient::class)->post(
            'api/v1/test',
            ['password' => 'raw-password-needed-for-support', 'safe' => 'value'],
            [],
            ['submission_id' => 123, 'invoice_id' => 456, 'action' => 'validate']
        );

        $files = Storage::disk('local')->allFiles('nrs-debug');
        $this->assertCount(1, $files);

        $artifact = json_decode(Storage::disk('local')->get($files[0]), true);
        $this->assertSame(123, $artifact['context']['submission_id']);
        $this->assertSame('[REDACTED]', $artifact['request']['headers']['x-api-key']);
        $this->assertSame('[REDACTED]', $artifact['request']['headers']['x-api-secret']);
        $this->assertSame('raw-password-needed-for-support', $artifact['request']['payload']['password']);
        $this->assertSame('raw-response-token', $artifact['response']['payload']['access_token']);

        $log = NrsApiLog::firstOrFail();
        $this->assertSame('[REDACTED]', $log->request_payload['password']);
        $this->assertSame('[REDACTED]', $log->response_body['access_token']);
    }
}
