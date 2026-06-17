<?php

namespace App\Services\Auth;

use App\DTOs\Auth\LoginDTO;
use App\DTOs\Auth\RegisterDTO;
use App\DTOs\Auth\ResendOtpDTO;
use App\DTOs\Auth\ResetPasswordDTO;
use App\DTOs\Auth\VerifyOtpDTO;
use App\DTOs\RequestContextDTO;
use App\Events\AccountSecurityAlertRequested;
use App\Models\User;
use App\Services\Operations\OperationalMetricService;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Password;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

class AuthService
{
    public function __construct(
        private readonly OtpService $otpService,
        private readonly OperationalMetricService $metrics,
    ) {}

    public function startRegistration(RegisterDTO $dto): array
    {
        $existingUser = User::where('email', $dto->email)->first();
        if ($existingUser?->email_verified_at) {
            throw ValidationException::withMessages([
                'email' => ['This email address is already registered.'],
            ]);
        }

        User::updateOrCreate(
            ['email' => $dto->email],
            [
                'first_name' => $dto->first_name,
                'last_name' => $dto->last_name,
                'password' => Hash::make($dto->password),
                'email_verified_at' => null,
            ]
        );

        $this->otpService->generate($dto->email, 'registration');

        return [
            'message' => 'A verification code has been sent to your email.',
            'requires_otp' => true,
            'email' => $dto->email,
        ];
    }

    public function startLogin(LoginDTO $dto, RequestContextDTO $context): array
    {
        $throttleKey = $this->loginThrottleKey($dto->email, $context->ip);

        if (RateLimiter::tooManyAttempts($throttleKey, 5)) {
            $lockedUser = User::where('email', $dto->email)->first();
            if ($lockedUser) {
                $this->dispatchLoginLockoutAlert($lockedUser, $context, RateLimiter::availableIn($throttleKey));
            }

            return [
                'message' => 'Too many failed login attempts. Please try again later.',
                'retry_after' => RateLimiter::availableIn($throttleKey),
                'status' => 429,
            ];
        }

        $user = User::where('email', $dto->email)->first();

        if (! $user || ! Hash::check($dto->password, $user->password)) {
            RateLimiter::hit($throttleKey, 900);
            $this->metrics->increment('auth_failure_spikes', 20, ['ip' => $context->ip]);

            if ($user && RateLimiter::tooManyAttempts($throttleKey, 5)) {
                $this->dispatchLoginLockoutAlert($user, $context, RateLimiter::availableIn($throttleKey));
            }

            throw ValidationException::withMessages([
                'email' => ['Invalid credentials.'],
            ]);
        }

        if ($user->suspended_at) {
            throw ValidationException::withMessages([
                'email' => ['This account has been suspended. Please contact support.'],
            ]);
        }

        RateLimiter::clear($throttleKey);
        $this->otpService->generate($user->email, 'login');

        return [
            'message' => 'A verification code has been sent to your email.',
            'requires_otp' => true,
            'email' => $user->email,
            'status' => 200,
        ];
    }

    public function verifyOtp(VerifyOtpDTO $dto, RequestContextDTO $context): User
    {
        $otp = $this->otpService->verify($dto->email, $dto->code, $dto->type);

        if (! $otp) {
            throw ValidationException::withMessages([
                'code' => ['Invalid or expired verification code.'],
            ]);
        }

        $user = User::where('email', $dto->email)->firstOrFail();

        if ($user->suspended_at) {
            throw ValidationException::withMessages([
                'email' => ['This account has been suspended. Please contact support.'],
            ]);
        }

        if ($dto->type === 'registration') {
            $user->forceFill(['email_verified_at' => now()])->save();
        } else {
            $this->dispatchNewSignInContextAlert($user, $context);
        }

        return $user;
    }

    public function resendOtp(ResendOtpDTO $dto, RequestContextDTO $context): void
    {
        $throttleKey = 'otp-resend:'.$dto->type.':'.Str::lower($dto->email).'|'.$context->ip;
        if (RateLimiter::tooManyAttempts($throttleKey, 3)) {
            abort(response()->json([
                'message' => 'Too many OTP resend attempts. Please wait before requesting another code.',
                'retry_after' => RateLimiter::availableIn($throttleKey),
            ], 429));
        }

        if ($dto->type === 'registration') {
            $user = User::where('email', $dto->email)->first();
            if (! $user || $user->email_verified_at) {
                throw ValidationException::withMessages([
                    'email' => ['No pending registration verification was found.'],
                ]);
            }
        } elseif (! User::where('email', $dto->email)->exists()) {
            throw ValidationException::withMessages([
                'email' => ['No account was found for this email address.'],
            ]);
        }

        RateLimiter::hit($throttleKey, 60);
        $this->otpService->generate($dto->email, $dto->type, forceNew: true);
    }

    public function resetPassword(ResetPasswordDTO $dto): void
    {
        $status = Password::reset(
            [
                'email' => $dto->email,
                'token' => $dto->token,
                'password' => $dto->password,
                'password_confirmation' => $dto->passwordConfirmation,
            ],
            function (User $user, string $password): void {
                $user->forceFill([
                    'password' => Hash::make($password),
                    'remember_token' => Str::random(60),
                ])->save();

                $user->tokens()->delete();
                $this->dispatchPasswordChangedAlert($user);
            }
        );

        if ($status !== Password::PASSWORD_RESET) {
            throw ValidationException::withMessages([
                'email' => [__($status)],
            ]);
        }
    }

    public function resetLinkStatus(string $email): string
    {
        return Password::sendResetLink(['email' => $email]);
    }

    private function loginThrottleKey(string $email, ?string $ip): string
    {
        return 'login:'.Str::lower($email).'|'.$ip;
    }

    private function frontendUrl(string $path = ''): string
    {
        $frontendUrl = rtrim((string) config('app.frontend_url', env('FRONTEND_URL', config('app.url'))), '/');

        return $path === '' ? $frontendUrl : $frontendUrl.'/'.ltrim($path, '/');
    }

    private function dispatchPasswordChangedAlert(User $user): void
    {
        AccountSecurityAlertRequested::dispatch(
            user: $user,
            subject: 'Your Veridex password was changed',
            heading: 'Password changed successfully',
            message: 'Your Veridex account password was changed successfully.',
            details: ['Date' => now()->format('M j, Y g:i A')],
            actionText: 'Open Veridex',
            actionUrl: $this->frontendUrl('/login'),
            footer: 'If you did not make this change, reset your password immediately and contact support.',
        );
    }

    private function dispatchLoginLockoutAlert(User $user, RequestContextDTO $context, int $retryAfter): void
    {
        $key = 'security-alert:login-lockout:'.$user->id.':'.hash('sha256', strtolower($user->email)).':'.$context->ip;
        if (! Cache::add($key, now()->toISOString(), 900)) {
            return;
        }

        AccountSecurityAlertRequested::dispatch(
            user: $user,
            subject: 'Too many failed Veridex login attempts',
            heading: 'Login attempts temporarily blocked',
            message: 'We temporarily blocked sign-in attempts for your Veridex account after repeated failed login attempts.',
            details: [
                'IP address' => (string) $context->ip,
                'Retry after' => ceil($retryAfter / 60).' minutes',
                'Date' => now()->format('M j, Y g:i A'),
            ],
            actionText: 'Reset Password',
            actionUrl: $this->frontendUrl('/forgot-password'),
            footer: 'If this was not you, reset your password and review your account access.',
        );
    }

    private function dispatchNewSignInContextAlert(User $user, RequestContextDTO $context): void
    {
        $fingerprint = hash('sha256', implode('|', [
            strtolower((string) $context->userAgent),
            (string) $context->ip,
        ]));
        $key = "known-login-context:{$user->id}:{$fingerprint}";

        if (! Cache::add($key, now()->toISOString(), now()->addYear())) {
            return;
        }

        AccountSecurityAlertRequested::dispatch(
            user: $user,
            subject: 'New Veridex sign-in',
            heading: 'New sign-in detected',
            message: 'Your Veridex account was accessed from a sign-in context we have not seen before.',
            details: [
                'IP address' => (string) $context->ip,
                'Browser' => mb_substr((string) $context->userAgent, 0, 120),
                'Date' => now()->format('M j, Y g:i A'),
            ],
            actionText: 'Open Veridex',
            actionUrl: $this->frontendUrl('/dashboard'),
            footer: 'If this was you, no action is needed. If this was not you, reset your password immediately.',
        );
    }
}
