<?php

namespace App\Services;

use App\Events\OtpRequested;
use App\Models\OtpCode;

class OtpService
{
    /**
     * Generate a new OTP code and dispatch the email event.
     */
    public function generate(string $email, string $type, ?array $payload = null): OtpCode
    {
        // Invalidate any previous active codes for this email + type
        OtpCode::where('email', $email)
            ->where('type', $type)
            ->whereNull('verified_at')
            ->delete();

        $code = str_pad((string) random_int(0, 999999), 6, '0', STR_PAD_LEFT);

        $otp = OtpCode::create([
            'email' => $email,
            'code' => $code,
            'type' => $type,
            'payload' => $payload,
            'expires_at' => now()->addMinutes(5),
        ]);

        // Fire event — listener sends email asynchronously
        OtpRequested::dispatch($email, $code, $type);

        return $otp;
    }

    /**
     * Verify an OTP code. Returns the OtpCode if valid, null otherwise.
     */
    public function verify(string $email, string $code, string $type): ?OtpCode
    {
        $otp = OtpCode::where('email', $email)
            ->where('code', $code)
            ->where('type', $type)
            ->whereNull('verified_at')
            ->first();

        if (! $otp) {
            return null;
        }

        if ($otp->isExpired()) {
            return null;
        }

        $otp->markVerified();

        return $otp;
    }
}
