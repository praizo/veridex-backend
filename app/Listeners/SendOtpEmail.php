<?php

namespace App\Listeners;

use App\Events\OtpRequested;
use App\Mail\OtpMail;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Support\Facades\Mail;

class SendOtpEmail implements ShouldQueue
{
    public function handle(OtpRequested $event): void
    {
        Mail::to($event->email)->send(new OtpMail($event->code, $event->type));
    }
}
