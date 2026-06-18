<?php

namespace App\Services\Profile;

use App\DTOs\Profile\UpdateProfileDTO;
use App\Events\AccountSecurityAlertRequested;
use App\Models\User;
use App\Services\ActivityLog\ActivityLogService;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\ValidationException;

class ProfileService
{
    public function __construct(
        private readonly ActivityLogService $activityLog,
    ) {}

    public function updateProfile(User $user, UpdateProfileDTO $dto): User
    {
        $before = $user->only(['first_name', 'last_name']);
        $passwordChanged = false;

        if ($dto->password) {
            if (! Hash::check((string) $dto->currentPassword, $user->password)) {
                throw ValidationException::withMessages([
                    'current_password' => ['The current password is incorrect.'],
                ]);
            }

            $user->password = Hash::make($dto->password);
            $user->tokens()->delete();
            $this->dispatchPasswordChangedAlert($user);
            $passwordChanged = true;
        }

        $user->first_name = $dto->firstName;
        $user->last_name = $dto->lastName;
        $user->save();

        $this->activityLog->log(
            $user,
            'profile.updated',
            $user,
            $passwordChanged ? 'Profile and password updated.' : 'Profile updated.',
            [
                'before' => $before,
                'after' => $user->only(['first_name', 'last_name']),
                'password_changed' => $passwordChanged,
            ],
        );

        return $user->fresh();
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
            actionUrl: rtrim((string) config('app.frontend_url', env('FRONTEND_URL', config('app.url'))), '/').'/login',
            footer: 'If you did not make this change, reset your password immediately and contact support.',
        );
    }
}
