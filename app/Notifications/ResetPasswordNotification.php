<?php

declare(strict_types=1);

namespace App\Notifications;

use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

final class ResetPasswordNotification extends Notification
{
    use Queueable;

    public function __construct(
        private readonly string $resetUrl
    ) {
    }

    /**
     * @return array<int, string>
     */
    public function via(object $notifiable): array
    {
        return ['mail'];
    }

    public function toMail(
        object $notifiable
    ): MailMessage {
        return (new MailMessage())
            ->subject('Reset your RushPi password')
            ->view(
                'emails.reset-password',
                [
                    'name' =>
                        $notifiable->name
                        ?? 'RushPi customer',
                    'resetUrl' =>
                        $this->resetUrl,
                    'supportEmail' =>
                        config('mail.from.address'),
                ]
            );
    }

    /**
     * @return array<string, mixed>
     */
    public function toArray(
        object $notifiable
    ): array {
        return [
            'reset_url' => $this->resetUrl,
        ];
    }
}
