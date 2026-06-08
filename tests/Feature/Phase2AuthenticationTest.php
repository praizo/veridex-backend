<?php

namespace Tests\Feature;

use App\Models\OtpCode;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Routing\Middleware\ThrottleRequests;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\Password;
use Tests\TestCase;

class Phase2AuthenticationTest extends TestCase
{
    use RefreshDatabase;

    public function test_registration_never_stores_plaintext_password_in_otp_payload(): void
    {
        Event::fake();

        $password = 'StrongPass1!';

        $this->postJson('/api/v1/register', [
            'name' => 'Secure User',
            'email' => 'secure@example.com',
            'password' => $password,
            'password_confirmation' => $password,
        ])->assertOk();

        $user = User::where('email', 'secure@example.com')->firstOrFail();
        $otp = OtpCode::where('email', 'secure@example.com')->firstOrFail();

        $this->assertNull($user->email_verified_at);
        $this->assertTrue(Hash::check($password, $user->password));
        $this->assertNull($otp->payload);
        $this->assertStringNotContainsString($password, json_encode($otp->toArray()));
    }

    public function test_expired_otp_cannot_be_used(): void
    {
        Event::fake();

        $user = User::factory()->create([
            'email' => 'expired@example.com',
            'email_verified_at' => null,
        ]);

        OtpCode::create([
            'email' => $user->email,
            'code' => '123456',
            'type' => 'registration',
            'expires_at' => now()->subMinute(),
        ]);

        $this->postJson('/api/v1/verify-otp', [
            'email' => $user->email,
            'code' => '123456',
            'type' => 'registration',
        ])->assertStatus(422);

        $this->assertNull($user->fresh()->email_verified_at);
    }

    public function test_used_otp_cannot_be_reused(): void
    {
        Event::fake();

        $user = User::factory()->create([
            'email' => 'reuse@example.com',
            'email_verified_at' => null,
        ]);

        OtpCode::create([
            'email' => $user->email,
            'code' => '123456',
            'type' => 'registration',
            'expires_at' => now()->addMinutes(5),
        ]);

        $payload = [
            'email' => $user->email,
            'code' => '123456',
            'type' => 'registration',
        ];

        $this->postJson('/api/v1/verify-otp', $payload)->assertCreated();
        $this->postJson('/api/v1/verify-otp', $payload)->assertStatus(422);
    }

    public function test_otp_attempt_limit_blocks_brute_force(): void
    {
        $this->withoutMiddleware(ThrottleRequests::class);
        Event::fake();

        $user = User::factory()->create([
            'email' => 'attempts@example.com',
            'email_verified_at' => null,
        ]);

        $otp = OtpCode::create([
            'email' => $user->email,
            'code' => '123456',
            'type' => 'registration',
            'expires_at' => now()->addMinutes(5),
            'max_attempts' => 5,
        ]);

        for ($i = 0; $i < 5; $i++) {
            $this->postJson('/api/v1/verify-otp', [
                'email' => $user->email,
                'code' => '000000',
                'type' => 'registration',
            ])->assertStatus(422);
        }

        $this->assertSame(5, $otp->fresh()->attempts);

        $this->postJson('/api/v1/verify-otp', [
            'email' => $user->email,
            'code' => '123456',
            'type' => 'registration',
        ])->assertStatus(422);

        $this->assertNull($user->fresh()->email_verified_at);
    }

    public function test_login_lockout_blocks_repeated_failed_attempts(): void
    {
        Event::fake();

        User::factory()->create([
            'email' => 'lockout@example.com',
            'password' => Hash::make('StrongPass1!'),
        ]);

        for ($i = 0; $i < 5; $i++) {
            $this->postJson('/api/v1/login', [
                'email' => 'lockout@example.com',
                'password' => 'WrongPass1!',
            ])->assertStatus(422);
        }

        $this->postJson('/api/v1/login', [
            'email' => 'lockout@example.com',
            'password' => 'WrongPass1!',
        ])->assertStatus(429);
    }

    public function test_password_reset_token_expires(): void
    {
        Notification::fake();

        $user = User::factory()->create(['email' => 'expired-reset@example.com']);
        $token = Password::createToken($user);

        DB::table('password_reset_tokens')
            ->where('email', $user->email)
            ->update(['created_at' => now()->subMinutes(61)]);

        $this->postJson('/api/v1/reset-password', [
            'email' => $user->email,
            'token' => $token,
            'password' => 'NewStrong1!',
            'password_confirmation' => 'NewStrong1!',
        ])->assertStatus(422);
    }

    public function test_password_reset_token_is_single_use(): void
    {
        Notification::fake();

        $user = User::factory()->create(['email' => 'single-use@example.com']);
        $token = Password::createToken($user);

        $payload = [
            'email' => $user->email,
            'token' => $token,
            'password' => 'NewStrong1!',
            'password_confirmation' => 'NewStrong1!',
        ];

        $this->postJson('/api/v1/reset-password', $payload)->assertOk();
        $this->postJson('/api/v1/reset-password', $payload)->assertStatus(422);
    }
}
