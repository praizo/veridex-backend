<?php

namespace App\Console\Commands;

use App\Models\User;
use Illuminate\Console\Command;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

class RevokePlatformSuperAdmin extends Command
{
    protected $signature = 'platform:revoke-super-admin {email : Email address to remove from platform super admin access}';

    protected $description = 'Unset platform-scoped super admin access for a user without deleting the user account.';

    public function handle(): int
    {
        $email = Str::lower(trim((string) $this->argument('email')));

        if (! filter_var($email, FILTER_VALIDATE_EMAIL)) {
            throw ValidationException::withMessages([
                'email' => ['A valid email address is required.'],
            ]);
        }

        $user = User::where('email', $email)->first();

        if (! $user) {
            $this->error('No user exists with that email.');

            return self::FAILURE;
        }

        $platformAdmin = $user->platformAdmin()->first();

        if (! $platformAdmin || $platformAdmin->status !== 'active' || $platformAdmin->role !== 'super_admin') {
            $this->warn("{$user->email} is not an active platform super admin.");

            return self::SUCCESS;
        }

        $platformAdmin->forceFill(['status' => 'revoked'])->save();

        $this->info("Platform super admin access revoked for {$user->email}.");

        return self::SUCCESS;
    }
}
