<?php

namespace App\Notifications;

use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

class OrganizationDeletedNotification extends Notification
{
    use Queueable;

    public string $orgName;
    public string $token;

    public function __construct(string $orgName, string $token)
    {
        $this->orgName = $orgName;
        $this->token = $token;
    }

    public function via(object $notifiable): array
    {
        return ['mail'];
    }

    public function toMail(object $notifiable): MailMessage
    {
        $frontendUrl = config('app.frontend_url');
        $restoreUrl = "{$frontendUrl}/organizations/restore?token={$this->token}&email=" . urlencode($notifiable->email);

        return (new MailMessage)
            ->subject('Organization Was Deleted - ' . config('app.name'))
            ->view('emails.organization-deleted', [
                'orgName' => $this->orgName,
                'restoreUrl' => $restoreUrl,
                'user' => $notifiable,
            ]);
    }
}