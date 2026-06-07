<?php

namespace Tests\Feature;

use App\Models\Organization;
use App\Models\User;
use App\Services\Nrs\NrsResourceService;
use Illuminate\Foundation\Testing\RefreshDatabase;
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
                ]
            ]);
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
                ]
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
                ]
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
                ]
            ]);

        // Test the services-codes alias route
        $response2 = $this->actingAs($this->user)
            ->getJson('/api/v1/resources/services-codes');

        $response2->assertOk()
            ->assertJson([
                'data' => [
                    ['code' => 'SVC1', 'description' => 'Service One'],
                ]
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
                ]
            ]);
    }
}
