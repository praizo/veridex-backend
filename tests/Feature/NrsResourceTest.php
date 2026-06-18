<?php

namespace Tests\Feature;

use App\Models\Organization;
use App\Models\User;
use App\Services\Nrs\NrsResourceService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Mockery\MockInterface;
use Tests\TestCase;

class NrsResourceTest extends TestCase
{
    use RefreshDatabase;

    protected User $user;

    protected Organization $organization;

    protected function setUp(): void
    {
        parent::setUp();

        $this->organization = Organization::create([
            'name' => 'Test Org',
            'slug' => 'test-org',
            'tin' => '12345678-0001',
            'email' => 'org@test.com',
        ]);

        $this->user = User::factory()->create([
            'current_organization_id' => $this->organization->id,
            'onboarding_completed_at' => now(),
        ]);

        $this->user->organizations()->attach($this->organization->id, ['role' => 'admin']);
    }

    public function test_can_retrieve_countries(): void
    {
        $this->mock(NrsResourceService::class, function (MockInterface $mock) {
            $mock->shouldReceive('getCountries')->once()->andReturn([
                ['code' => 'NG', 'name' => 'Nigeria'],
                ['code' => 'US', 'name' => 'United States'],
            ]);
        });

        $response = $this->actingAs($this->user)
            ->getJson('/api/v1/resources/countries');

        $response->assertOk()
            ->assertJson([
                'data' => [
                    ['code' => 'NG', 'name' => 'Nigeria'],
                    ['code' => 'US', 'name' => 'United States'],
                ],
            ]);
    }

    public function test_can_retrieve_aggregated_resources(): void
    {
        $this->mock(NrsResourceService::class, function (MockInterface $mock) {
            $mock->shouldReceive('getHsCodes')->once()->andReturn([['code' => '1001']]);
            $mock->shouldReceive('getCurrencies')->once()->andReturn([['code' => 'NGN']]);
            $mock->shouldReceive('getTaxCategories')->once()->andReturn([['code' => 'STANDARD_VAT']]);
            $mock->shouldReceive('getInvoiceTypes')->once()->andReturn([['code' => '380']]);
            $mock->shouldReceive('getPaymentMeans')->once()->andReturn([['code' => '10']]);
            $mock->shouldReceive('getServiceCodes')->once()->andReturn([['code' => 'SVC1']]);
            $mock->shouldReceive('getCountries')->once()->andReturn([['code' => 'NG', 'name' => 'Nigeria']]);
            $mock->shouldReceive('getLgas')->once()->andReturn([['code' => 'LGA1']]);
            $mock->shouldReceive('getStates')->once()->andReturn([['code' => 'LA']]);
            $mock->shouldReceive('getVatExemptions')->once()->andReturn([['code' => 'VAT_EX1']]);
        });

        $response = $this->actingAs($this->user)
            ->getJson('/api/v1/resources');

        $response->assertOk()
            ->assertJsonPath('data.hs_codes.0.code', '1001')
            ->assertJsonPath('data.currencies.0.code', 'NGN')
            ->assertJsonPath('data.tax_categories.0.code', 'STANDARD_VAT')
            ->assertJsonPath('data.invoice_types.0.code', '380')
            ->assertJsonPath('data.payment_means.0.code', '10')
            ->assertJsonPath('data.service_codes.0.code', 'SVC1')
            ->assertJsonPath('data.countries.0.code', 'NG')
            ->assertJsonPath('data.lgas.0.code', 'LGA1')
            ->assertJsonPath('data.states.0.code', 'LA')
            ->assertJsonPath('data.vat_exemptions.0.code', 'VAT_EX1');
    }

    public function test_can_retrieve_lgas(): void
    {
        $this->mock(NrsResourceService::class, function (MockInterface $mock) {
            $mock->shouldReceive('getLgas')->once()->andReturn([
                ['code' => 'LGA1', 'name' => 'LGA One'],
            ]);
        });

        $response = $this->actingAs($this->user)
            ->getJson('/api/v1/resources/lgas');

        $response->assertOk()
            ->assertJson([
                'data' => [
                    ['code' => 'LGA1', 'name' => 'LGA One'],
                ],
            ]);
    }

    public function test_can_retrieve_states(): void
    {
        $this->mock(NrsResourceService::class, function (MockInterface $mock) {
            $mock->shouldReceive('getStates')->once()->andReturn([
                ['code' => 'LA', 'name' => 'Lagos'],
            ]);
        });

        $response = $this->actingAs($this->user)
            ->getJson('/api/v1/resources/states');

        $response->assertOk()
            ->assertJson([
                'data' => [
                    ['code' => 'LA', 'name' => 'Lagos'],
                ],
            ]);
    }

    public function test_can_retrieve_services_codes(): void
    {
        $this->mock(NrsResourceService::class, function (MockInterface $mock) {
            $mock->shouldReceive('getServiceCodes')->twice()->andReturn([
                ['code' => 'SVC1', 'description' => 'Service One'],
            ]);
        });

        // Test the standard route
        $response1 = $this->actingAs($this->user)
            ->getJson('/api/v1/resources/service-codes');

        $response1->assertOk()
            ->assertJson([
                'data' => [
                    ['code' => 'SVC1', 'description' => 'Service One'],
                ],
            ]);

        // Test the services-codes alias route
        $response2 = $this->actingAs($this->user)
            ->getJson('/api/v1/resources/services-codes');

        $response2->assertOk()
            ->assertJson([
                'data' => [
                    ['code' => 'SVC1', 'description' => 'Service One'],
                ],
            ]);
    }

    public function test_can_retrieve_vat_exemptions(): void
    {
        $this->mock(NrsResourceService::class, function (MockInterface $mock) {
            $mock->shouldReceive('getVatExemptions')->once()->andReturn([
                ['code' => 'VAT_EX1', 'reason' => 'Exemption Reason'],
            ]);
        });

        $response = $this->actingAs($this->user)
            ->getJson('/api/v1/resources/vat-exemptions');

        $response->assertOk()
            ->assertJson([
                'data' => [
                    ['code' => 'VAT_EX1', 'reason' => 'Exemption Reason'],
                ],
            ]);
    }

    public function test_countries_api_mapping_resolves_alpha_2_to_code(): void
    {
        Cache::forget('nrs_countries');
        Cache::forget('nrs_countries:stale');

        Http::fake([
            '*/api/v1/invoice/resources/countries' => Http::response([
                'data' => [
                    [
                        'name' => 'Afghanistan',
                        'alpha_2' => 'AF',
                        'alpha_3' => 'AFG',
                        'country_code' => '004',
                    ],
                    [
                        'name' => 'Nigeria',
                        'alpha_2' => 'NG',
                        'alpha_3' => 'NGA',
                        'country_code' => '566',
                    ],
                ],
            ], 200),
        ]);

        $service = app(NrsResourceService::class);
        $result = $service->getCountries();

        $this->assertEquals([
            ['code' => 'AF', 'name' => 'Afghanistan'],
            ['code' => 'NG', 'name' => 'Nigeria'],
        ], $result);
    }

    public function test_resource_fetch_caches_successful_responses_as_fresh_and_stale(): void
    {
        Cache::forget('nrs_currencies');
        Cache::forget('nrs_currencies:stale');

        Http::fake([
            '*/api/v1/invoice/resources/currencies' => Http::response([
                'data' => [
                    ['code' => 'NGN', 'name' => 'Naira'],
                ],
            ], 200),
        ]);

        $service = app(NrsResourceService::class);
        $result = $service->getCurrencies();

        $this->assertEquals([
            ['code' => 'NGN', 'name' => 'Naira'],
        ], $result);
        $this->assertSame($result, Cache::get('nrs_currencies'));
        $this->assertSame($result, Cache::get('nrs_currencies:stale'));
    }

    public function test_resource_fetch_returns_stale_cache_when_nrs_fails(): void
    {
        Cache::forget('nrs_currencies');
        Cache::put('nrs_currencies:stale', [
            ['code' => 'NGN', 'name' => 'Naira'],
        ], now()->addDays(30));

        Http::fake([
            '*/api/v1/invoice/resources/currencies' => Http::response([
                'message' => 'NRS unavailable',
            ], 503),
        ]);

        $service = app(NrsResourceService::class);
        $result = $service->getCurrencies();

        $this->assertEquals([
            ['code' => 'NGN', 'name' => 'Naira'],
        ], $result);
        $this->assertNull(Cache::get('nrs_currencies'));
    }
}
