<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\OnboardingRequest;
use App\Models\Organization;
use App\Services\Nrs\NrsClient;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;

/**
 * Handles real-time verification of Taxpayer data during onboarding.
 */
class OnboardingController extends Controller
{
    public function __construct(
        protected NrsClient $nrsClient
    ) {}

    /**
     * Complete business onboarding — creates Organization and stamps the user.
     */
    public function completeOnboarding(OnboardingRequest $request): JsonResponse
    {
        $user = $request->user();

        if ($user->hasCompletedOnboarding()) {
            return response()->json([
                'message' => 'Onboarding already completed.',
                'user' => $user->load('currentOrganization'),
            ]);
        }

        $validated = $request->validated();

        $org = Organization::create([
            'name' => $validated['organization_name'],
            'slug' => str()->slug($validated['organization_name']).'-'.str()->random(5),
            'email' => $user->email,
            'tin' => $validated['tin'],
            'nrs_business_id' => $validated['nrs_business_id'],
            'service_id' => $validated['service_id'],
            'telephone' => $validated['telephone'],
            'street_name' => $validated['street_name'],
            'city_name' => $validated['city_name'],
            'postal_zone' => $validated['postal_zone'],
            'country_code' => $validated['country_code'],
        ]);

        $user->organizations()->attach($org->id, ['role' => 'owner']);
        $user->update([
            'current_organization_id' => $org->id,
            'onboarding_completed_at' => now(),
        ]);

        return response()->json([
            'message' => 'Onboarding completed successfully.',
            'user' => $user->fresh()->load('currentOrganization'),
        ], 201);
    }

    /**
     * Verify a TIN and retrieve company metadata.
     * Uses a 'Smart Simulation' mode for Sandbox if the official lookup is unavailable.
     */
    public function verifyTin(Request $request): JsonResponse
    {
        $request->validate([
            'tin' => ['required', 'string', 'regex:/^\d{8}-\d{4}$/'],
        ]);

        $tin = $request->tin;

        // FIRS Sandbox Data Mock (since public lookup often isn't in the collection)
        $simulatedData = [
            '20484017-0001' => ['name' => 'GMRiteMate Global', 'status' => 'ACTIVE', 'category' => 'MEDIUM'],
            '18609323-0001' => ['name' => 'Sandbox Trading Ltd', 'status' => 'ACTIVE', 'category' => 'SMALL'],
            '99999999-0001' => ['name' => 'Veridex Dummy Taxpayer Ltd', 'status' => 'ACTIVE', 'category' => 'MEDIUM'],
        ];

        try {
            // Attempt a real check if your FIRS collection had a verify endpoint (placeholder)
            // $response = $this->nrsClient->get("api/v1/taxpayer/verify?tin={$tin}");

            if (isset($simulatedData[$tin])) {
                return response()->json([
                    'success' => true,
                    'is_valid' => true,
                    'data' => $simulatedData[$tin],
                ]);
            }

            // Basic Structural Validation for Nigerian TIN (usually 12 digits or 8 digits)
            $isFormatValid = preg_match('/^\d{8}-\d{4}$/', $tin);

            if ($isFormatValid) {
                return response()->json([
                    'success' => true,
                    'is_valid' => true,
                    'message' => 'TIN format valid. Structural verification passed.',
                    'data' => [
                        'name' => null, // We don't have the name from the API yet
                        'status' => 'UNVERIFIED',
                    ],
                ]);
            }

            return response()->json([
                'success' => false,
                'is_valid' => false,
                'message' => 'Invalid TIN format. Please check the number and try again.',
            ], 422);

        } catch (\Exception $e) {
            Log::error('TIN Verification Error: '.$e->getMessage());

            return response()->json(['success' => false, 'message' => 'Verification service temporarily unavailable.'], 500);
        }
    }
}
