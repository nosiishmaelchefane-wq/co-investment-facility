<?php

namespace App\Notifications;

use App\Models\User;
use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;
use Illuminate\Support\Facades\Password;

/**
 * E-mail with a one-time link to set a password — uses Laravel's password-reset flow (routes password.reset / password.store).
 *   type 'invite' → new account invitation   (broker "invites", link valid 72 h — config/auth.php)
 *   type 'reset'  → password reset sent by an administrator (default broker, usually 60 min)
 */
class AccountAccessLink extends Notification
{
    use Queueable;

    public function __construct(public string $type = 'invite') {}

    public function via(object $notifiable): array
    {
        return ['mail'];
    }

    /** Broker used to create (and later check) the token */
    public static function brokerFor(string $type): string
    {
        $default = config('auth.defaults.passwords', 'users');

        // Falls back to the default broker if the "invites" broker isn't in config/auth.php yet
        return $type === 'invite' && config('auth.passwords.invites') ? 'invites' : $default;
    }

    public function toMail(object $notifiable): MailMessage
    {
        /** @var User $notifiable */
        $broker  = self::brokerFor($this->type);
        $token   = Password::broker($broker)->createToken($notifiable);
        $url     = route('password.reset', array_filter([
            'token'  => $token,
            'email'  => $notifiable->getEmailForPasswordReset(),
            'invite' => $this->type === 'invite' ? 1 : null,
        ]));
        $minutes = (int) config("auth.passwords.$broker.expire", 60);
        $expires = $minutes >= 120 ? intdiv($minutes, 60).' hours' : $minutes.' minutes';
        $app     = config('app.name', 'Co-Investment Facility');

        if ($this->type === 'reset') {
            return (new MailMessage)
                ->subject("Reset your {$app} password")
                ->greeting("Hello {$notifiable->name},")
                ->line('An administrator has sent you a link to reset your password.')
                ->action('Choose a new password', $url)
                ->line("This link expires in {$expires} and can only be used once.")
                ->line('If you did not expect this email, you can ignore it — your current password still works.');
        }

        $role = $notifiable->roles->first()?->display_name;

        return (new MailMessage)
            ->subject("You've been invited to the {$app}")
            ->greeting("Hello {$notifiable->name},")
            ->line('An account has been created for you on the LEHSFF Co-Investment Facility'.($role ? " as **{$role}**." : '.'))
            ->line('Set your password to sign in:')
            ->action('Set up my account', $url)
            ->line("This link expires in {$expires} and can only be used once.");
    }
}