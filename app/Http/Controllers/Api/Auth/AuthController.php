<?php

namespace App\Http\Controllers\Api\Auth;

use App\DTOs\Auth\LoginDTO;
use App\DTOs\Auth\RegisterDTO;
use App\DTOs\Auth\ResendOtpDTO;
use App\DTOs\Auth\ResetPasswordDTO;
use App\DTOs\Auth\VerifyOtpDTO;
use App\DTOs\RequestContextDTO;
use App\Http\Controllers\Controller;
use App\Http\Requests\Auth\ForgotPasswordRequest;
use App\Http\Requests\Auth\LoginRequest;
use App\Http\Requests\Auth\RegisterRequest;
use App\Http\Requests\Auth\ResendOtpRequest;
use App\Http\Requests\Auth\ResetPasswordRequest;
use App\Http\Requests\Auth\VerifyOtpRequest;
use App\Services\ActivityLog\ActivityLogService;
use App\Services\Auth\AuthService;
use App\Services\Auth\AuthSessionService;
use App\Traits\HasUserPayload;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Password;

class AuthController extends Controller
{
    use HasUserPayload;

    public function __construct(
        private readonly AuthService $authService,
        private readonly AuthSessionService $sessionService,
        private readonly ActivityLogService $activityLog,
    ) {}

    public function register(RegisterRequest $request): JsonResponse
    {
        $this->sessionService->clear($request);

        return response()->json($this->authService->startRegistration(RegisterDTO::fromRequest($request)));
    }

    public function login(LoginRequest $request): JsonResponse
    {
        $this->sessionService->clear($request);

        $payload = $this->authService->startLogin(
            LoginDTO::fromRequest($request),
            RequestContextDTO::fromRequest($request),
        );

        return response()->json(
            collect($payload)->except('status')->all(),
            $payload['status'] ?? 200,
        );
    }

    public function verifyOtp(VerifyOtpRequest $request): JsonResponse
    {
        $user = $this->authService->verifyOtp(
            VerifyOtpDTO::fromRequest($request),
            RequestContextDTO::fromRequest($request),
        );

        $this->sessionService->login($request, $user);

        $this->activityLog->log(
            $user,
            $request->type === 'registration' ? 'auth.registered' : 'auth.login',
            $user,
            $request->type === 'registration' ? 'User completed registration.' : 'User signed in.',
        );

        return response()->json([
            'user' => $this->userPayload($user),
        ], $request->type === 'registration' ? 201 : 200);
    }

    public function resendOtp(ResendOtpRequest $request): JsonResponse
    {
        $this->authService->resendOtp(
            ResendOtpDTO::fromRequest($request),
            RequestContextDTO::fromRequest($request),
        );

        return response()->json([
            'message' => 'A new verification code has been sent.',
        ]);
    }

    public function forgotPassword(ForgotPasswordRequest $request): JsonResponse
    {
        $status = $this->authService->resetLinkStatus($request->validated('email'));

        return response()->json([
            'message' => __($status === Password::RESET_LINK_SENT ? $status : 'If the email exists, a password reset link has been sent.'),
        ], $status === Password::RESET_THROTTLED ? 429 : 200);
    }

    public function resetPassword(ResetPasswordRequest $request): JsonResponse
    {
        $this->authService->resetPassword(ResetPasswordDTO::fromRequest($request));

        return response()->json([
            'message' => __(Password::PASSWORD_RESET),
        ]);
    }

    public function logout(Request $request): JsonResponse
    {
        $user = $request->user();

        if ($user) {
            $this->activityLog->log(
                $user,
                'auth.logout',
                $user,
                'User signed out.',
            );
        }

        $this->sessionService->clear($request);

        return response()
            ->json(['message' => 'Logged out successfully'])
            ->withoutCookie((string) config('session.cookie'), (string) config('session.path'), config('session.domain'))
            ->withoutCookie('XSRF-TOKEN', (string) config('session.path'), config('session.domain'));
    }

    public function me(): JsonResponse
    {
        return response()->json($this->userPayload(request()->user()));
    }
}
