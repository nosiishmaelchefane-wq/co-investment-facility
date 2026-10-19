<?php

namespace App\Notifications;

use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

/**
 * Account settings · security alert e-mail (Laravel default mail layout).
 *   email     → sent to the OLD address when the e-mail address changes
 *   password  → the password was changed
 *   two_factor→ two-step sign-in was switched on / off
 */
class AccountSecurityChanged extends Notification
{
    use Queueable;

    public function __construct(public string $event, public array $data = []) {}

    public function via(object $notifiable): array
    {
        return ['mail'];
    }

    public function toMail(object $notifiable): MailMessage
    {
        $when = now()->format('d M Y \a\t H:i');
        $ip   = $this->data['ip'] ?? null;

        $mail = match ($this->event) {
            'email' => (new MailMessage)
                ->subject('Your sign-in e-mail address was changed')
                ->line("The e-mail address for your account was changed on {$when}.")
                ->line('**New address:** '.($this->data['new'] ?? '—'))
                ->line('From now on, sign in and receive e-mails at the new address.'),

            'password' => (new MailMessage)
                ->subject('Your password was changed')
                ->line("The password for your account was changed on {$when}."),

            'two_factor' => (new MailMessage)
                ->subject(($this->data['on'] ?? false) ? 'Two-step sign-in was turned on' : 'Two-step sign-in was turned off')
                ->line(($this->data['on'] ?? false)
                    ? "Two-step sign-in was turned on for your account on {$when}. You'll be asked for an e-mailed code when you sign in."
                    : "Two-step sign-in was turned off for your account on {$when}. Only your password is now needed to sign in."),

            default => (new MailMessage)->subject('Your account was updated')->line("Your account was updated on {$when}."),
        };

        if ($ip) {
            $mail->line('Request made from IP address '.$ip.'.');
        }

        return $mail->line("**If this wasn't you**, contact the LEHSFF team straight away.");
    }
}