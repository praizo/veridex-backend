<?php

namespace App\Services;

use App\Events\OtpRequested;
use App\Models\OtpCode;
use Illuminate\Support\Facades\Log;

class OtpService
{
    /**
     * Generate a new OTP code and dispatch the email event.
     */
    public function generate(string $email, string $type, ?array $payload = null, bool $forceNew = false): OtpCode
    {
        if (! $forceNew) {
            $existing = OtpCode::active()
                ->where('email', $email)
                ->where('type', $type)
                ->where('created_at', '>=', now()->subSeconds(30))
                ->latest()
                ->first();

            if ($existing) {
                Log::info('OTP generation reused recent active code', [
                    'email_hash' => hash('sha256', strtolower($email)),
                    'type' => $type,
                    'otp_id' => $existing->id,
                ]);

                return $existing;
            }
        }

        // Invalidate any previous active codes for this email + type
        OtpCode::where('email', $email)
            ->where('type', $type)
            ->whereNull('verified_at')
            ->whereNull('consumed_at')
            ->delete();

        $code = str_pad((string) random_int(0, 999999), 6, '0', STR_PAD_LEFT);

        $otp = OtpCode::create([
            'email' => $email,
            'code' => $code,
            'type' => $type,
            'payload' => $payload,
            'attempts' => 0,
            'max_attempts' => 5,
            'expires_at' => now()->addMinutes(5),
        ]);

        // Fire event — listener sends email asynchronously
        OtpRequested::dispatch($email, $code, $type);

        Log::info('OTP email event dispatched', [
            'email_hash' => hash('sha256', strtolower($email)),
            'type' => $type,
            'otp_id' => $otp->id,
            'queue_connection' => config('queue.default'),
        ]);

        return $otp;
    }

    /**
     * Verify an OTP code. Returns the OtpCode if valid, null otherwise.
     */
    public function verify(string $email, string $code, string $type): ?OtpCode
    {
        $otp = OtpCode::where('email', $email)
            ->where('type', $type)
            ->whereNull('verified_at')
            ->whereNull('consumed_at')
            ->latest()
            ->first();

        if (! $otp) {
            return null;
        }

        if ($otp->isExpired() || ! $otp->hasAttemptsRemaining()) {
            return null;
        }

        if (! hash_equals($otp->code, $code)) {
            $otp->recordFailedAttempt();

            return null;
        }

        $otp->markConsumed();

        return $otp;
    }
}
