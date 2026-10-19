<?php

namespace App\Notifications;

use App\Models\ExpressionOfInterest;
use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;
use Illuminate\Support\Facades\Route;

/**
 * G3 · Every (non-recused) Committee member has submitted their scorecard.
 * Sent to whoever records the Committee outcome (gate3.decide).
 */
class EoiScorecardsComplete extends Notification
{
    use Queueable;

    public function __construct(
        public ExpressionOfInterest $eoi,
        public int $submitted,
        public int $round = 1,
    ) {}

    public function via(object $notifiable): array
    {
        return ['mail'];
    }

    public function toMail(object $notifiable): MailMessage
    {
        $eoi = $this->eoi;

        return (new MailMessage)
            ->subject('All scorecards in: '.$eoi->reference.($eoi->business_name ? ' — '.$eoi->business_name : ''))
            ->greeting('Hello '.($notifiable->name ?: '').',')
            ->line('All Investment Committee members have submitted their scorecards for **'.($eoi->business_name ?: $eoi->reference).'** '
                .'(reference **'.$eoi->reference.'**'.($this->round > 1 ? ', round '.$this->round : '').') — '
                .$this->submitted.' '.($this->submitted === 1 ? 'scorecard' : 'scorecards').'.')
            ->line('The **consolidated scorecard** is now visible on the G3 tab.')
            ->line('Next: complete the Committee record (criteria views, deliberation, conditions) and **record the Committee outcome**.')
            ->action('Open the application', Route::has('expression-of-interest.show')
                ? route('expression-of-interest.show', ['id' => $eoi->id])
                : url('/'));
    }
}