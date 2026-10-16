<?php

namespace App\Notifications;

use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

class WorkspaceInvited extends Notification
{
    public function __construct(private string $workspace, private string $inviter, private string $token) {}

    public function via(object $notifiable): array
    {
        return ['mail'];
    }

    public function toMail(object $notifiable): MailMessage
    {
        return (new MailMessage)
            ->subject("دعوت به میزکار {$this->workspace}")
            ->greeting('سلام!')
            ->line("{$this->inviter} شما را به میزکار «{$this->workspace}» دعوت کرده است.")
            ->action('پذیرفتن دعوت', route('invitations.show', $this->token))
            ->line('این لینک تا ۷ روز معتبر است. اگر انتظار این دعوت را نداشتید، این ایمیل را نادیده بگیرید.');
    }
}
