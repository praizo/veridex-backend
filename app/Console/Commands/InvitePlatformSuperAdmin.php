<?php

namespace App\Console\Commands;

use App\Models\User;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Password;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

class InvitePlatformSuperAdmin extends Command
{
    protected $signature = 'platform:invite-super-admin
        {email : Email address for the platform operator}
        {--first-name= : Operator first name}
        {--last-name= : Operator last name}
        {--no-email : Create/grant access without sending a password setup email}';

    protected $description = 'Create or update a platform-only super admin and send a password setup link.';

    public function handle(): int
    {
        $email = Str::lower(trim((string) $this->argument('email')));

        if (! filter_var($email, FILTER_VALIDATE_EMAIL)) {
            throw ValidationException::withMessages([
                'email' => ['A valid email address is required.'],
            ]);
        }

        $user = User::where('email', $email)->first();
        $created = false;

        if (! $user) {
            $user = User::create([
                'first_name' => $this->option('first-name') ?: 'Platform',
                'last_name' => $this->option('last-name') ?: 'Operator',
                'email' => $email,
                'email_verified_at' => now(),
                'password' => Hash::make(Str::random(64)),
                'current_organization_id' => null,
                'onboarding_completed_at' => null,
            ]);
            $created = true;
        } else {
            if ($user->current_organization_id || $user->organizations()->exists()) {
                $this->error('Organization users cannot be granted platform super admin access.');

                return self::FAILURE;
            }

            $updates = [];

            if ($this->option('first-name')) {
                $updates['first_name'] = $this->option('first-name');
            }

            if ($this->option('last-name')) {
                $updates['last_name'] = $this->option('last-name');
            }

            if (! $user->email_verified_at) {
                $updates['email_verified_at'] = now();
            }

            if ($updates !== []) {
                $user->forceFill($updates)->save();
            }
        }

        $user->platformAdmin()->updateOrCreate(
            ['user_id' => $user->id],
            [
                'role' => 'super_admin',
                'status' => 'active',
            ],
        );

        $this->info(($created ? 'Created' : 'Updated').' platform super admin: '.$user->email);

        if ($this->option('no-email')) {
            $this->warn('Password setup email was not sent because --no-email was used.');

            return self::SUCCESS;
        }

        $status = Password::sendResetLink(['email' => $user->email]);

        if ($status !== Password::RESET_LINK_SENT) {
            $this->error('Platform access was granted, but the password setup email could not be sent: '.__($status));

            return self::FAILURE;
        }

        $this->info('Password setup email sent.');

        return self::SUCCESS;
    }
}
