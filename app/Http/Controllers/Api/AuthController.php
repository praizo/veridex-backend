<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\Auth\ForgotPasswordRequest;
use App\Http\Requests\Auth\LoginRequest;
use App\Http\Requests\Auth\RegisterRequest;
use App\Http\Requests\Auth\ResendOtpRequest;
use App\Http\Requests\Auth\ResetPasswordRequest;
use App\Http\Requests\Auth\VerifyOtpRequest;
use App\Models\User;
use App\Services\OperationalMetricService;
use App\Services\OtpService;
use App\Traits\HasUserPayload;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Password;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

class AuthController extends Controller
{
    use HasUserPayload;

    public function __construct(
        private readonly OtpService $otpService,
        private readonly OperationalMetricService $metrics,
    ) {}

    private function clearAuthenticatedSession(Request $request): void
    {
        $accessToken = $request->user()?->currentAccessToken();
        if ($accessToken && method_exists($accessToken, 'delete')) {
            $accessToken->delete();
        }

        auth('web')->logout();

        if ($request->hasSession()) {
            $request->session()->invalidate();
            $request->session()->regenerateToken();
        }
    }

    /**
     * Register — validates data, stores it as OTP payload, sends OTP email.
     * Does NOT create the user yet.
     */
    public function register(RegisterRequest $request): JsonResponse
    {
        $validated = $request->validated();

        $existingUser = User::where('email', $validated['email'])->first();
        if ($existingUser?->email_verified_at) {
            throw ValidationException::withMessages([
                'email' => ['This email address is already registered.'],
            ]);
        }

        $user = User::updateOrCreate(
            ['email' => $validated['email']],
            [
                'first_name' => $validated['first_name'],
                'last_name' => $validated['last_name'],
                'password' => Hash::make($validated['password']),
                'email_verified_at' => null,
            ]
        );

        $this->clearAuthenticatedSession($request);
        $this->otpService->generate($user->email, 'registration');

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
        $throttleKey = $this->loginThrottleKey($request);

        if (RateLimiter::tooManyAttempts($throttleKey, 5)) {
            return response()->json([
                'message' => 'Too many failed login attempts. Please try again later.',
                'retry_after' => RateLimiter::availableIn($throttleKey),
            ], 429);
        }

        $user = User::where('email', $validated['email'])->first();

        if (! $user || ! Hash::check($validated['password'], $user->password)) {
            RateLimiter::hit($throttleKey, 900);
            $this->metrics->increment('auth_failure_spikes', 20, [
                'ip' => $request->ip(),
            ]);

            throw ValidationException::withMessages([
                'email' => ['Invalid credentials.'],
            ]);
        }

        RateLimiter::clear($throttleKey);
        $this->clearAuthenticatedSession($request);
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
    public function verifyOtp(VerifyOtpRequest $request): JsonResponse
    {

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
            $user = User::where('email', $request->email)->firstOrFail();
            $user->forceFill(['email_verified_at' => now()])->save();

            auth('web')->login($user);
            if ($request->hasSession()) {
                $request->session()->regenerate();
            }

            return response()->json([
                'user' => $this->userPayload($user),
            ], 201);
        }

        // Login flow
        $user = User::where('email', $request->email)->firstOrFail();
        auth('web')->login($user);
        if ($request->hasSession()) {
            $request->session()->regenerate();
        }

        return response()->json([
            'user' => $this->userPayload($user),
        ]);
    }

    /**
     * Resend OTP — generates a fresh code for an existing flow.
     */
    public function resendOtp(ResendOtpRequest $request): JsonResponse
    {

        $throttleKey = 'otp-resend:'.$request->type.':'.Str::lower($request->email).'|'.$request->ip();
        if (RateLimiter::tooManyAttempts($throttleKey, 3)) {
            return response()->json([
                'message' => 'Too many OTP resend attempts. Please wait before requesting another code.',
                'retry_after' => RateLimiter::availableIn($throttleKey),
            ], 429);
        }

        if ($request->type === 'registration') {
            $user = User::where('email', $request->email)->first();
            if (! $user || $user->email_verified_at) {
                throw ValidationException::withMessages([
                    'email' => ['No pending registration verification was found.'],
                ]);
            }
        } else {
            if (! User::where('email', $request->email)->exists()) {
                throw ValidationException::withMessages([
                    'email' => ['No account was found for this email address.'],
                ]);
            }
        }

        RateLimiter::hit($throttleKey, 60);
        $this->otpService->generate($request->email, $request->type, forceNew: true);

        return response()->json([
            'message' => 'A new verification code has been sent.',
        ]);
    }

    public function forgotPassword(ForgotPasswordRequest $request): JsonResponse
    {

        $status = Password::sendResetLink($request->only('email'));

        return response()->json([
            'message' => __($status === Password::RESET_LINK_SENT ? $status : 'If the email exists, a password reset link has been sent.'),
        ], $status === Password::RESET_THROTTLED ? 429 : 200);
    }

    public function resetPassword(ResetPasswordRequest $request): JsonResponse
    {

        $status = Password::reset(
            $request->only('email', 'password', 'password_confirmation', 'token'),
            function (User $user, string $password): void {
                $user->forceFill([
                    'password' => Hash::make($password),
                    'remember_token' => Str::random(60),
                ])->save();

                $user->tokens()->delete();
            }
        );

        if ($status !== Password::PASSWORD_RESET) {
            throw ValidationException::withMessages([
                'email' => [__($status)],
            ]);
        }

        return response()->json([
            'message' => __($status),
        ]);
    }

    public function logout(Request $request): JsonResponse
    {
        $accessToken = $request->user()?->currentAccessToken();
        if ($accessToken && method_exists($accessToken, 'delete')) {
            $accessToken->delete();
        }

        $this->clearAuthenticatedSession($request);

        return response()
            ->json(['message' => 'Logged out successfully'])
            ->withoutCookie((string) config('session.cookie'), (string) config('session.path'), config('session.domain'))
            ->withoutCookie('XSRF-TOKEN', (string) config('session.path'), config('session.domain'));
    }

    public function me(): JsonResponse
    {
        return response()->json($this->userPayload(request()->user()));
    }

    private function loginThrottleKey(Request $request): string
    {
        return 'login:'.Str::lower((string) $request->input('email')).'|'.$request->ip();
    }
}
