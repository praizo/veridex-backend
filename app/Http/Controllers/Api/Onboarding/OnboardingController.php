<?php

namespace App\Http\Controllers\Api\Onboarding;

use App\Http\Controllers\Controller;
use App\Http\Requests\OnboardingRequest;
use App\Http\Requests\VerifyTinRequest;
use App\Services\Nrs\NrsClient;
use App\Services\Onboarding\OnboardingService;
use App\Traits\HasUserPayload;
use Illuminate\Http\JsonResponse;
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
    public function verifyTin(VerifyTinRequest $request): JsonResponse
    {
        try {
            return response()->json($this->onboardingService->verifyTin($request->validated('tin')));
        } catch (\Exception $e) {
            Log::error('TIN Verification Error: '.$e->getMessage());

            return response()->json(['success' => false, 'message' => 'Verification service temporarily unavailable.'], 500);
        }
    }
}
