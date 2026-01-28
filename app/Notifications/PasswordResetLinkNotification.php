<?php

namespace App\Notifications;

use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

class PasswordResetLinkNotification extends Notification
{
    private string $resetUrl;
    private bool $adminInitiated;

    public function __construct(string $resetUrl, bool $adminInitiated = false)
    {
        $this->resetUrl = $resetUrl;
        $this->adminInitiated = $adminInitiated;
    }

    /**
     * @param  mixed  $notifiable
     * @return array<int, string>
     */
    public function via($notifiable): array
    {
        return ['mail'];
    }

    /**
     * @param  mixed  $notifiable
     */
    public function toMail($notifiable): MailMessage
    {
        $message = new MailMessage();

        $subject = $this->adminInitiated
            ? 'Ustaw nowe haslo (reset admina)'
            : 'Reset hasla';

        return $message
            ->subject($subject)
            ->line('Otrzymales link do ustawienia nowego hasla.')
            ->action('Ustaw nowe haslo', $this->resetUrl)
            ->line('Jesli nie prosiles o zmiane hasla, zignoruj te wiadomosc.');
    }
}
