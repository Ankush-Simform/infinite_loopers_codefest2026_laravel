<?php

declare(strict_types=1);

namespace App\Notifications\Auth;

use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

final class VerifyEmailWithJwt extends Notification
{
    use Queueable;

    public function __construct(
        private readonly string $verificationToken,
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
        return (new MailMessage)
            ->subject('Verify your email address')
            ->line('Please verify your email address before logging in.')
            ->action('Verify email', $this->verificationUrl())
            ->line('If you did not create this account, no further action is required.');
    }

    private function verificationUrl(): string
    {
        $baseUrl = rtrim((string) config('auth.email_verification.frontend_verify_url'), '/');
        $separator = str_contains($baseUrl, '?') ? '&' : '?';

        return $baseUrl . $separator . 'token=' . urlencode($this->verificationToken);
    }
}
