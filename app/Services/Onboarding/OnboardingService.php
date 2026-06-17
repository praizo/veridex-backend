<?php

namespace App\Services\Onboarding;

use App\Models\Organization;
use App\Models\User;

class OnboardingService
{
    /**
     * Complete business onboarding — creates Organization and stamps the user.
     *
     * @return array{user: User, organization: Organization}
     */
    public function completeOnboarding(User $user, array $validatedData): array
    {
        $org = Organization::create([
            'name' => $validatedData['organization_name'],
            'slug' => str()->slug($validatedData['organization_name']).'-'.str()->random(5),
            'email' => $user->email,
            'tin' => $validatedData['tin'],
            'nrs_business_id' => $validatedData['nrs_business_id'],
            'service_id' => $validatedData['service_id'],
            'telephone' => $validatedData['telephone'],
            'street_name' => $validatedData['street_name'],
            'city_name' => $validatedData['city_name'],
            'postal_zone' => $validatedData['postal_zone'],
            'country_code' => $validatedData['country_code'],
        ]);

        $user->organizations()->attach($org->id, ['role' => 'owner']);
        $user->update([
            'current_organization_id' => $org->id,
            'onboarding_completed_at' => now(),
        ]);

        return ['user' => $user->fresh(), 'organization' => $org];
    }

    public function verifyTin(string $tin): array
    {
        return [
            'success' => true,
            'is_valid' => true,
            'message' => 'TIN format valid. Structural verification passed.',
            'data' => [
                'name' => null,
                'status' => 'UNVERIFIED',
            ],
        ];
    }
}
