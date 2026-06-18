<?php

namespace Tests\Feature;

use App\Events\OtpRequested;
use App\Models\OtpCode;
use App\Models\User;
use App\Notifications\VeridexAlertNotification;
use App\Services\Auth\OtpService;
use Illuminate\Auth\SessionGuard;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Routing\Middleware\ThrottleRequests;
use Illuminate\Support\Facades\Cache;
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
            'first_name' => 'Secure User', 'last_name' => 'Last',
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

    public function test_rapid_duplicate_otp_generation_reuses_active_code_without_resending_email(): void
    {
        Event::fake();

        $service = app(OtpService::class);

        $first = $service->generate('duplicate@example.com', 'login');
        $second = $service->generate('duplicate@example.com', 'login');

        $this->assertSame($first->id, $second->id);
        $this->assertSame(1, OtpCode::where('email', 'duplicate@example.com')->where('type', 'login')->count());
        Event::assertDispatchedTimes(OtpRequested::class, 1);
    }

    public function test_forced_otp_resend_creates_fresh_code_and_email_event(): void
    {
        Event::fake();

        $service = app(OtpService::class);

        $first = $service->generate('resend@example.com', 'login');
        $second = $service->generate('resend@example.com', 'login', forceNew: true);

        $this->assertNotSame($first->id, $second->id);
        $this->assertSame(1, OtpCode::where('email', 'resend@example.com')->where('type', 'login')->active()->count());
        Event::assertDispatchedTimes(OtpRequested::class, 2);
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

    public function test_login_lockout_sends_security_notification(): void
    {
        Cache::flush();
        Notification::fake();

        $user = User::factory()->create([
            'email' => 'lockout-alert@example.com',
            'password' => Hash::make('StrongPass1!'),
        ]);

        for ($i = 0; $i < 5; $i++) {
            $this->postJson('/api/v1/login', [
                'email' => 'lockout-alert@example.com',
                'password' => 'WrongPass1!',
            ])->assertStatus(422);
        }

        $this->postJson('/api/v1/login', [
            'email' => 'lockout-alert@example.com',
            'password' => 'WrongPass1!',
        ])->assertStatus(429);

        Notification::assertSentToTimes($user, VeridexAlertNotification::class, 1);
    }

    public function test_login_otp_challenge_clears_existing_authenticated_session(): void
    {
        Event::fake();
        $sessionKey = $this->webSessionAuthKey();

        $existingUser = User::factory()->create([
            'email' => 'existing-session@example.com',
            'password' => Hash::make('StrongPass1!'),
        ]);

        $loginUser = User::factory()->create([
            'email' => 'otp-login@example.com',
            'password' => Hash::make('StrongPass1!'),
        ]);

        $this->withSession([$sessionKey => $existingUser->getAuthIdentifier()]);
        $this->getJson('/api/v1/me')->assertOk();

        $this->postJson('/api/v1/login', [
            'email' => $loginUser->email,
            'password' => 'StrongPass1!',
        ])->assertOk()
            ->assertJson(['requires_otp' => true]);

        $this->assertGuest('web');
        $this->assertFalse(session()->has($sessionKey));
    }

    public function test_successful_login_from_new_context_sends_security_notification(): void
    {
        Notification::fake();

        $user = User::factory()->create([
            'email' => 'new-context@example.com',
            'password' => Hash::make('StrongPass1!'),
        ]);

        $this->withHeader('User-Agent', 'Feature Test Browser/1.0')
            ->postJson('/api/v1/login', [
                'email' => $user->email,
                'password' => 'StrongPass1!',
            ])->assertOk()
            ->assertJson(['requires_otp' => true]);

        $otp = OtpCode::where('email', $user->email)
            ->where('type', 'login')
            ->firstOrFail();

        $this->withHeader('User-Agent', 'Feature Test Browser/1.0')
            ->postJson('/api/v1/verify-otp', [
                'email' => $user->email,
                'code' => $otp->code,
                'type' => 'login',
            ])->assertOk();

        Notification::assertSentTo($user, VeridexAlertNotification::class);
    }

    public function test_registration_otp_challenge_clears_existing_authenticated_session(): void
    {
        Event::fake();
        $sessionKey = $this->webSessionAuthKey();

        $existingUser = User::factory()->create([
            'email' => 'existing-register-session@example.com',
            'password' => Hash::make('StrongPass1!'),
        ]);

        $this->withSession([$sessionKey => $existingUser->getAuthIdentifier()]);
        $this->getJson('/api/v1/me')->assertOk();

        $this->postJson('/api/v1/register', [
            'first_name' => 'Pending User', 'last_name' => 'Last',
            'email' => 'pending-registration@example.com',
            'password' => 'StrongPass1!',
            'password_confirmation' => 'StrongPass1!',
        ])->assertOk()
            ->assertJson(['requires_otp' => true]);

        $this->assertGuest('web');
        $this->assertFalse(session()->has($sessionKey));
    }

    public function test_logout_clears_authenticated_session(): void
    {
        $sessionKey = $this->webSessionAuthKey();

        $user = User::factory()->create([
            'email' => 'logout-session@example.com',
            'password' => Hash::make('StrongPass1!'),
        ]);

        $this->withSession([$sessionKey => $user->getAuthIdentifier()]);
        $this->getJson('/api/v1/me')->assertOk();

        $this->postJson('/api/v1/logout')->assertOk();

        $this->assertGuest('web');
        $this->assertFalse(session()->has($sessionKey));
    }

    private function webSessionAuthKey(): string
    {
        return 'login_web_'.sha1(SessionGuard::class);
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

        Notification::assertSentToTimes($user, VeridexAlertNotification::class, 1);
    }
}
