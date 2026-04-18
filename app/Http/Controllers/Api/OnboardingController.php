<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use App\Services\Nrs\NrsClient;
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
     * Verify a TIN and retrieve company metadata.
     * Uses a 'Smart Simulation' mode for Sandbox if the official lookup is unavailable.
     */
    public function verifyTin(Request $request): JsonResponse
    {
        $request->validate([
            'tin' => 'required|string|min:8|max:20'
        ]);

        $tin = $request->tin;

        // FIRS Sandbox Data Mock (since public lookup often isn't in the collection)
        $simulatedData = [
            '20484017' => ['name' => 'GMRiteMate Global', 'status' => 'ACTIVE', 'category' => 'MEDIUM'],
            '18609323-0001' => ['name' => 'Sandbox Trading Ltd', 'status' => 'ACTIVE', 'category' => 'SMALL'],
        ];

        try {
            // Attempt a real check if your FIRS collection had a verify endpoint (placeholder)
            // $response = $this->nrsClient->get("api/v1/taxpayer/verify?tin={$tin}");
            
            if (isset($simulatedData[$tin])) {
                return response()->json([
                    'success' => true,
                    'is_valid' => true,
                    'data' => $simulatedData[$tin]
                ]);
            }

            // Basic Structural Validation for Nigerian TIN (usually 12 digits or 8 digits)
            $isFormatValid = preg_match('/^\d{8,12}(-\d{4})?$/', $tin);

            if ($isFormatValid) {
                return response()->json([
                    'success' => true,
                    'is_valid' => true,
                    'message' => 'TIN format valid. Structural verification passed.',
                    'data' => [
                        'name' => null, // We don't have the name from the API yet
                        'status' => 'UNVERIFIED',
                    ]
                ]);
            }

            return response()->json([
                'success' => false,
                'is_valid' => false,
                'message' => 'Invalid TIN format. Please check the number and try again.'
            ], 422);

        } catch (\Exception $e) {
            Log::error("TIN Verification Error: " . $e->getMessage());
            return response()->json(['success' => false, 'message' => 'Verification service temporarily unavailable.'], 500);
        }
    }
}
