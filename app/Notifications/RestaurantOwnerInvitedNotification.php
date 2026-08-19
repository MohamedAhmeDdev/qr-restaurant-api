<?php

namespace App\Notifications;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

class RestaurantOwnerInvitedNotification extends Notification implements ShouldQueue
{
    use Queueable;

    public string $inviteUrl;
    public $expiresAt;

    public function __construct(string $inviteUrl, $expiresAt)
    {
        $this->inviteUrl = $inviteUrl;
        $this->expiresAt = $expiresAt;
    }

    public function via($notifiable): array
    {
        return ['mail'];
    }

    public function toMail($notifiable): MailMessage
    {
        return (new MailMessage)
            ->subject('Invitation to Join QRRestaurant')
            ->greeting('Hello!')
            ->line('You have been invited to set up your restaurant on the QRRestaurant platform.')
            ->action('Complete Onboarding', $this->inviteUrl)
            ->line('This invitation link will expire on ' . $this->expiresAt->format('M d, Y H:i A') . '.')
            ->line('If you did not expect this invitation, no action is required.');
    }
}