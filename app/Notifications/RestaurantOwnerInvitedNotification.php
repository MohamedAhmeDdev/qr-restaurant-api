<?php

namespace App\Notifications;

use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

class RestaurantOwnerInvitedNotification extends Notification
{
    public string $token;
    public $expiresAt;

    public function __construct(string $token, $expiresAt)
    {
        $this->token = $token;
        $this->expiresAt = $expiresAt;
    }

    public function via($notifiable): array
    {
        return ['mail'];
    }

    public function toMail($notifiable): MailMessage
    {
      $frontendUrl = config('app.frontend_url');
        $inviteUrl = "{$frontendUrl}/register?token={$this->token}";

        return (new MailMessage)
            ->subject('Invitation to Join ' . config('app.name', 'QRRestaurant'))
            ->view('emails.owner-invitation', [
                'inviteUrl' => $inviteUrl,
                'formattedExpiresAt' => $this->expiresAt->format('M d, Y \a\t H:i A'),
            ]);
    }
}