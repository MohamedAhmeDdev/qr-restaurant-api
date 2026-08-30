<?php

namespace App\Notifications;

use App\Models\Restaurant;
use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

class StaffWelcomeNotification extends Notification
{
    use Queueable;

    public string $plainPassword;
    public Restaurant $restaurant;

    public function __construct(string $plainPassword, Restaurant $restaurant)
    {
        $this->plainPassword = $plainPassword;
        $this->restaurant = $restaurant;
    }

    public function via(object $notifiable): array
    {
        return ['mail'];
    }

    public function toMail(object $notifiable): MailMessage
    {
        $loginUrl = config('app.frontend_url') . '/login';
        $role = $notifiable->roles->first();

        return (new MailMessage)
            ->subject('Welcome to ' . $this->restaurant->name . ' - Your Account Details')
            ->view('emails.staff-welcome', [
                'user'          => $notifiable,
                'role'          => $role,
                'plainPassword' => $this->plainPassword,
                'restaurant'    => $this->restaurant,
                'loginUrl'      => $loginUrl,
            ]);
    }
}