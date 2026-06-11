<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\OnboardingRequest;
use App\Models\Organization;
use App\Services\Nrs\NrsClient;
use App\Services\OnboardingService;
use App\Traits\HasUserPayload;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;

/**
 * Handles real-time verification of Taxpayer data during onboarding.
 */
class OnboardingController extends Controller
{
    use HasUserPayload;

    public function __construct(
        protected NrsClient $nrsClient,
        protected OnboardingService $onboardingService
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
                'user' => $this->userPayload($user),
            ]);
        }

        $result = $this->onboardingService->completeOnboarding($user, $request->validated());

        return response()->json([
            'message' => 'Onboarding completed successfully.',
            'user' => $this->userPayload($result['user']),
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

        try {
            // Attempt a real check if your FIRS collection had a verify endpoint (placeholder)
            // $response = $this->nrsClient->get("api/v1/taxpayer/verify?tin={$tin}");

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
