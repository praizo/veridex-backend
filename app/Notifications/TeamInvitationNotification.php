<?php

namespace App\Notifications;

use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

class TeamInvitationNotification extends Notification
{
    use Queueable;

    public function __construct(
        private readonly string $organizationName,
        private readonly string $inviterName,
        private readonly string $role,
        private readonly string $actionUrl,
        private readonly bool $requiresPasswordSetup,
    ) {}

    /**
     * @return array<int, string>
     */
    public function via(object $notifiable): array
    {
        return ['mail'];
    }

    public function toMail(object $notifiable): MailMessage
    {
        $action = $this->requiresPasswordSetup ? 'Set Password' : 'Open Veridex';

        return (new MailMessage)
            ->subject("You've been added to {$this->organizationName} on Veridex")
            ->action($action, $this->actionUrl)
            ->view('emails.team-invitation', [
                'recipientName' => $notifiable->name,
                'organizationName' => $this->organizationName,
                'inviterName' => $this->inviterName,
                'roleLabel' => str($this->role)->headline()->toString(),
                'requiresPasswordSetup' => $this->requiresPasswordSetup,
                'actionText' => $action,
                'actionUrl' => $this->actionUrl,
            ]);
    }
}
