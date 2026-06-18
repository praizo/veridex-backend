<?php

namespace App\Console\Commands;

use App\Models\User;
use Illuminate\Console\Command;

class GrantPlatformSuperAdmin extends Command
{
    protected $signature = 'platform:super-admin {email} {--revoke : Unset platform super admin access}';

    protected $description = 'Grant or unset platform-scoped super admin access for an existing user.';

    public function handle(): int
    {
        $user = User::where('email', $this->argument('email'))->first();

        if (! $user) {
            $this->error('No user exists with that email. Create the user account first, then run this command.');

            return self::FAILURE;
        }

        if ($this->option('revoke')) {
            $platformAdmin = $user->platformAdmin()->first();

            if (! $platformAdmin || $platformAdmin->status !== 'active') {
                $this->warn("{$user->email} is not an active platform super admin.");

                return self::SUCCESS;
            }

            $platformAdmin->forceFill(['status' => 'revoked'])->save();
            $this->info("Platform super admin access revoked for {$user->email}.");

            return self::SUCCESS;
        }

        if ($user->current_organization_id || $user->organizations()->exists()) {
            $this->error('Organization users cannot be granted platform super admin access.');

            return self::FAILURE;
        }

        $user->platformAdmin()->updateOrCreate(
            ['user_id' => $user->id],
            [
                'role' => 'super_admin',
                'status' => 'active',
            ],
        );

        $this->info("Platform super admin access granted to {$user->email}.");

        return self::SUCCESS;
    }
}
