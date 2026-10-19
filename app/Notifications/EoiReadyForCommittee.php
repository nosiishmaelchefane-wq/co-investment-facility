<?php

namespace App\Notifications;

use App\Models\DueDiligence;
use App\Models\ExpressionOfInterest;
use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;
use Illuminate\Support\Facades\Route;

/**
 * G2 → G3 · Sent to Investment Committee members when due diligence is concluded with "Proceed".
 * Shows the validated figures (Maloti with US$) and links to the application.
 */
class EoiReadyForCommittee extends Notification
{
    use Queueable;

    /**
     * $round / $resubmissionNote: set when a deferred case is resubmitted to the Committee (G3 → new round).
     */
    public function __construct(
        public ExpressionOfInterest $eoi,
        public DueDiligence $dd,
        public int $round = 1,
        public ?string $resubmissionNote = null,
    ) {}

    public function via(object $notifiable): array
    {
        return ['mail'];
    }

    public function toMail(object $notifiable): MailMessage
    {
        $eoi  = $this->eoi;
        $dd   = $this->dd;
        $rate = (float) config('cif.usd_to_lsl', 18.00);

        $money = function ($lsl) use ($rate) {
            return $lsl === null || $lsl === ''
                ? '—'
                : 'M '.number_format((float) $lsl).' (US$ '.number_format((float) $lsl / max($rate, 0.0001)).')';
        };

        $private = $dd->private_amount;
        $grant   = $dd->grant_amount;
        $total   = $private === null && $grant === null ? null : (float) $private + (float) $grant;

        $resubmitted = $this->round > 1;

        $mail = (new MailMessage)
            ->subject(($resubmitted ? 'Resubmitted for Committee review (round '.$this->round.'): ' : 'For Committee review: ')
                .$eoi->reference.($eoi->business_name ? ' — '.$eoi->business_name : ''))
            ->greeting('Hello '.($notifiable->name ?: '').',')
            ->line($resubmitted
                ? 'The deferred case **'.($eoi->business_name ?: $eoi->reference).'** (reference **'.$eoi->reference.'**) '
                    .'has been resubmitted to the **Investment Committee** as round '.$this->round.'. A new scorecard is needed from each member.'
                : 'Due diligence on **'.($eoi->business_name ?: $eoi->reference).'** (reference **'.$eoi->reference.'**) '
                    .'has been concluded and the application is ready for **Investment Committee evaluation** (Gate 3).');

        if ($resubmitted && filled($this->resubmissionNote)) {
            $mail->line('**How the Committee’s requests were addressed:**')
                 ->line('> '.str_replace(["\r\n", "\n"], ' ', $this->resubmissionNote));
        }

        $mail->line('**Investor(s):** '.($dd->investor_names ?: ($eoi->investor_name ?: '—')))
            ->line('**Private capital committed:** '.$money($private))
            ->line('**CAFI grant:** '.$money($grant))
            ->line('**Total transaction:** '.$money($total));

        if ($dd->esgc_category && isset(DueDiligence::RATINGS[$dd->esgc_category])) {
            $mail->line('**ESGC risk:** '.DueDiligence::RATINGS[$dd->esgc_category]);
        }

        if (filled($dd->summary)) {
            $mail->line('**Summary:** '.str_replace(["\r\n", "\n"], ' ', $dd->summary));
        }

        return $mail
            ->action('Open the application', $this->applicationUrl())
            ->line('The Transaction Summary Sheet and the Investment Appraisal Memorandum are on the application’s Committee tab.');
    }

    protected function applicationUrl(): string
    {
        return Route::has('expression-of-interest.show')
            ? route('expression-of-interest.show', ['id' => $this->eoi->id])
            : url('/');
    }
}