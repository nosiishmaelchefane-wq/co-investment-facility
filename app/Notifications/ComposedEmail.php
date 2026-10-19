<?php

namespace App\Notifications;

use App\Models\SentEmail;
use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\HtmlString;

/**
 * Compose Email · the e-mail written on /compose-email, sent with Laravel's default mail layout (logo in the header).
 * Subject and body arrive already merged ({name}, {reference}… replaced) and cleaned.
 */
class ComposedEmail extends Notification
{
    use Queueable;

    /**
     * @param array $attachments [{name, path, size}] on SentEmail::DISK
     * @param array $cc          e-mail addresses
     * @param array $bcc         e-mail addresses
     */
    public function __construct(
        public string $subject,
        public string $html,
        public array $attachments = [],
        public array $cc = [],
        public array $bcc = [],
    ) {}

    public function via(object $notifiable): array
    {
        return ['mail'];
    }

    public function toMail(object $notifiable): MailMessage
    {
        $mail = (new MailMessage)
            ->subject($this->subject)
            ->greeting(' ')            // the message has its own "Dear …" — no "Hello!"
            ->salutation(' ')          // and its own sign-off — no "Regards, app name"
            ->line(new HtmlString($this->html));

        if ($this->cc)  { $mail->cc($this->cc); }
        if ($this->bcc) { $mail->bcc($this->bcc); }

        $disk = Storage::disk(SentEmail::DISK);
        foreach ($this->attachments as $a) {
            if (! empty($a['path']) && $disk->exists($a['path'])) {
                $mail->attach($disk->path($a['path']), ['as' => $a['name'] ?? basename($a['path'])]);
            }
        }

        return $mail;
    }
}