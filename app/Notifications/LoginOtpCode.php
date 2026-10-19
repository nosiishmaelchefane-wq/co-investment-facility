<?php

namespace App\Notifications;

use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

/**
 * Two-step sign-in: the one-time code e-mailed after a correct password.
 * Uses Laravel's default mail template (resources/views/vendor/mail — logo in the header).
 */
class LoginOtpCode extends Notification
{
    use Queueable;

    public function __construct(public string $code, public int $minutes) {}

    public function via(object $notifiable): array
    {
        return ['mail'];
    }

    public function toMail(object $notifiable): MailMessage
    {
        return (new MailMessage)
            ->subject('Your sign-in code: '.$this->code)
            ->greeting('Hello '.($notifiable->name ?: '').',')
            ->line('Use this code to finish signing in to '.config('app.name').':')
            ->line('# '.$this->code)
            ->line("The code expires in {$this->minutes} minutes and can only be used once.")
            ->line('If you did not try to sign in, ignore this e-mail and consider changing your password.');
    }
}