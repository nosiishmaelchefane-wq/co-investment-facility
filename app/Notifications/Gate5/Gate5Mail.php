<?php

namespace App\Notifications\Gate5;

use App\Models\ConditionReview;
use App\Models\ExpressionOfInterest;
use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;
use Illuminate\Support\Facades\Route;

/**
 * Shared helpers for the Gate 5 (conditions precedent, agreements, onboarding, tranches) e-mails.
 * Laravel's default mail template is used (resources/views/vendor/mail — logo in the header).
 */
abstract class Gate5Mail extends Notification
{
    use Queueable;

    public function __construct(
        public ExpressionOfInterest $eoi,
        public ConditionReview $review,
    ) {}

    public function via(object $notifiable): array
    {
        return ['mail'];
    }

    protected function start(object $notifiable, string $subject): MailMessage
    {
        return (new MailMessage)
            ->subject($subject)
            ->greeting('Hello '.($notifiable->name ?: '').',');
    }

    /** "M 450,000 (US$ 25,000)" */
    protected function money($lsl): string
    {
        if ($lsl === null || $lsl === '') {
            return '—';
        }
        $rate = (float) config('cif.usd_to_lsl', 18.00);

        return 'M '.number_format((float) $lsl).' (US$ '.number_format((float) $lsl / max($rate, 0.0001)).')';
    }

    protected function forBusiness(): string
    {
        return $this->eoi->business_name ? ' for **'.$this->eoi->business_name.'**' : '';
    }

    protected function ref(): string
    {
        return '**'.$this->eoi->reference.'**';
    }

    protected function quote(?string $text): string
    {
        return '> '.str_replace(["\r\n", "\n"], ' ', (string) $text);
    }

    protected function url(): string
    {
        return Route::has('expression-of-interest.show')
            ? route('expression-of-interest.show', ['id' => $this->eoi->id]).'?tab=g5'
            : url('/');
    }
}