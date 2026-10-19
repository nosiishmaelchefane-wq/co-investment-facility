<?php

namespace App\Notifications;

use App\Models\EligibilityScreening;
use App\Models\ExpressionOfInterest;
use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;
use Illuminate\Support\Facades\Route;

/**
 * G1 · Completeness review: the EOI was returned to the applicant for correction.
 * Lists the items marked "Not met" (with the officer's notes) and the officer's message.
 * Uses Laravel's default mail template (resources/views/vendor/mail — logo in the header).
 */
class EoiReturnedForCorrection extends Notification
{
    use Queueable;

    public function __construct(
        public ExpressionOfInterest $eoi,
        public EligibilityScreening $screening,
    ) {}

    public function via(object $notifiable): array
    {
        return ['mail'];
    }

    public function toMail(object $notifiable): MailMessage
    {
        $eoi   = $this->eoi;
        $items = $this->screening->outstandingItems();

        $mail = (new MailMessage)
            ->subject('Action needed: your application '.$eoi->reference.' was returned for correction')
            ->greeting('Hello '.($notifiable->name ?: '').',')
            ->line('Thank you for your Transaction Expression of Interest'
                .($eoi->business_name ? ' for **'.$eoi->business_name.'**' : '')
                .' (reference **'.$eoi->reference.'**).')
            ->line('The LEHSFF team reviewed it for completeness and found that some information is missing or needs correcting. '
                .'Your application has been returned to you so you can update it.');

        if ($items) {
            $mail->line('**Please attend to the following:**');
            foreach ($items as $i => $item) {
                $mail->line(($i + 1).'. **'.$item['title'].'**'.(filled($item['note']) ? ' — '.$item['note'] : ''));
            }
        }

        if (filled($this->screening->comments)) {
            $mail->line('**Message from the LEHSFF team:**')
                 ->line('> '.str_replace(["\r\n", "\n"], ' ', $this->screening->comments));
        }

        return $mail
            ->action('Update my application', $this->applicationUrl())
            ->line('Once you have made the changes to your EOI and enterprise profile, resubmit the application. '
                .'It will then be reviewed again.')
            ->line('If you have any questions, reply to this e-mail or contact the LEHSFF team.');
    }

    /** Link to the application page (falls back to the site root if the route is missing) */
    protected function applicationUrl(): string
    {
        return Route::has('expression-of-interest.show')
            ? route('expression-of-interest.show', ['id' => $this->eoi->id])
            : url('/');
    }
}