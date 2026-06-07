<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\Auth\LoginRequest;
use App\Http\Requests\Auth\RegisterRequest;
use App\Models\OtpCode;
use App\Models\User;
use App\Services\OtpService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\ValidationException;

class AuthController extends Controller
{
    public function __construct(
        private readonly OtpService $otpService,
    ) {}

    /**
     * Register — validates data, stores it as OTP payload, sends OTP email.
     * Does NOT create the user yet.
     */
    public function register(RegisterRequest $request): JsonResponse
    {
        $validated = $request->validated();

        // Store registration data as payload in the OTP record
        $this->otpService->generate($validated['email'], 'registration', $validated);

        return response()->json([
            'message' => 'A verification code has been sent to your email.',
            'requires_otp' => true,
            'email' => $validated['email'],
        ]);
    }

    /**
     * Login — validates credentials, sends OTP email.
     * Does NOT issue a token yet.
     */
    public function login(LoginRequest $request): JsonResponse
    {
        $validated = $request->validated();

        $user = User::where('email', $validated['email'])->first();

        if (! $user || ! Hash::check($validated['password'], $user->password)) {
            throw ValidationException::withMessages([
                'email' => ['Invalid credentials.'],
            ]);
        }

        $this->otpService->generate($user->email, 'login');

        return response()->json([
            'message' => 'A verification code has been sent to your email.',
            'requires_otp' => true,
            'email' => $user->email,
        ]);
    }

    /**
     * Verify OTP — creates user (for registration) or issues token (for login).
     */
    public function verifyOtp(Request $request): JsonResponse
    {
        $request->validate([
            'email' => ['required', 'email'],
            'code' => ['required', 'string', 'size:6'],
            'type' => ['required', 'in:registration,login'],
        ]);

        $otp = $this->otpService->verify(
            $request->email,
            $request->code,
            $request->type,
        );

        if (! $otp) {
            throw ValidationException::withMessages([
                'code' => ['Invalid or expired verification code.'],
            ]);
        }

        if ($request->type === 'registration') {
            $payload = $otp->payload;

            // Check if user was already created (e.g., OTP resend race condition)
            $user = User::where('email', $payload['email'])->first();

            if (! $user) {
                $user = User::create([
                    'name' => $payload['name'],
                    'email' => $payload['email'],
                    'password' => Hash::make($payload['password']),
                ]);
            }

            $token = $user->createToken('auth_token')->plainTextToken;

            return response()->json([
                'user' => $user->load('currentOrganization'),
                'token' => $token,
            ], 201);
        }

        // Login flow
        $user = User::where('email', $request->email)->firstOrFail();
        $token = $user->createToken('auth_token')->plainTextToken;

        return response()->json([
            'user' => $user->load('currentOrganization'),
            'token' => $token,
        ]);
    }

    /**
     * Resend OTP — generates a fresh code for an existing flow.
     */
    public function resendOtp(Request $request): JsonResponse
    {
        $request->validate([
            'email' => ['required', 'email'],
            'type' => ['required', 'in:registration,login'],
        ]);

        // For registration, try to find existing payload
        $payload = null;
        if ($request->type === 'registration') {
            $existingOtp = OtpCode::where('email', $request->email)
                ->where('type', 'registration')
                ->whereNotNull('payload')
                ->latest()
                ->first();

            $payload = $existingOtp?->payload;
        }

        $this->otpService->generate($request->email, $request->type, $payload);

        return response()->json([
            'message' => 'A new verification code has been sent.',
        ]);
    }

    public function logout(): JsonResponse
    {
        request()->user()->currentAccessToken()->delete();

        return response()->json(['message' => 'Logged out successfully']);
    }

    public function me(): JsonResponse
    {
        return response()->json(request()->user()->load('currentOrganization'));
    }
}
