<?php

namespace App\Listeners;

use App\Events\OtpRequested;
use App\Mail\OtpMail;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Queue\Middleware\WithoutOverlapping;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;
use Throwable;

class SendOtpEmail implements ShouldQueue
{
    public int $tries = 3;

    public int $timeout = 30;

    public function backoff(): array
    {
        return [10, 30, 60];
    }

    public function handle(OtpRequested $event): void
    {
        Log::info('OTP email job started', [
            'email_hash' => hash('sha256', strtolower($event->email)),
            'type' => $event->type,
            'queue_connection' => config('queue.default'),
        ]);

        Mail::to($event->email)->send(new OtpMail($event->code, $event->type));

        Log::info('OTP email job completed', [
            'email_hash' => hash('sha256', strtolower($event->email)),
            'type' => $event->type,
        ]);
    }

    public function middleware(OtpRequested $event): array
    {
        $key = 'otp-email:'.$event->type.':'.strtolower($event->email).':'.$event->code;

        return [(new WithoutOverlapping($key))->dontRelease()->expireAfter(300)];
    }

    public function failed(OtpRequested $event, Throwable $exception): void
    {
        Log::error('OTP email job failed', [
            'email_hash' => hash('sha256', strtolower($event->email)),
            'type' => $event->type,
            'exception' => $exception::class,
            'message' => $exception->getMessage(),
        ]);
    }
}
