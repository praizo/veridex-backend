<?php

namespace App\Notifications;

use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

class VeridexAlertNotification extends Notification
{
    use Queueable;

    public function __construct(
        private readonly string $subject,
        private readonly string $heading,
        private readonly string $message,
        private readonly array $details = [],
        private readonly ?string $actionText = null,
        private readonly ?string $actionUrl = null,
        private readonly ?string $footer = null,
    ) {}

    public function via(object $notifiable): array
    {
        return ['mail'];
    }

    public function toMail(object $notifiable): MailMessage
    {
        return (new MailMessage)
            ->subject($this->subject)
            ->view('emails.veridex-alert', [
                'recipientName' => $notifiable->name,
                'heading' => $this->heading,
                'messageText' => $this->message,
                'details' => $this->details,
                'actionText' => $this->actionText,
                'actionUrl' => $this->actionUrl,
                'footer' => $this->footer,
            ]);
    }
}
