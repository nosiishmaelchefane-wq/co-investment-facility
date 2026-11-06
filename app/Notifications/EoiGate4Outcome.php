<?php

namespace App\Notifications;

use App\Models\Authorisation;
use App\Models\ExpressionOfInterest;
use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;
use Illuminate\Support\Facades\Route;

/**
 * G5 · Final authorisation result e-mailed to the applicant (matches what they see on the G5 tab):
 *   Formally approved → both signatures on the Grant Agreement; next: conditions precedent & onboarding (+ conditions still to be met). Not a payment.
 *   Not approved      → the grant will not proceed.
 * A clarification round is internal (the PMU contacts the applicant if anything is needed), so no e-mail for it.
 */
class EoiGate4Outcome extends Notification
{
    use Queueable;

    public function __construct(
        public ExpressionOfInterest $eoi,
        public Authorisation $auth,
    ) {}

    public function via(object $notifiable): array
    {
        return ['mail'];
    }

    public function toMail(object $notifiable): MailMessage
    {
        $mail = (new MailMessage)->greeting('Hello '.($notifiable->name ?: '').',');

        return $this->auth->outcome === Authorisation::OUTCOME_AUTHORISED
            ? $this->authorised($mail)
            : $this->notAuthorised($mail);
    }

    protected function authorised(MailMessage $mail): MailMessage
    {
        $eoi  = $this->eoi;
        $a    = $this->auth;
        $rate = (float) config('cif.usd_to_lsl', 18.00);

        $mail->subject('Congratulations: your grant for '.$eoi->reference.' has been formally approved')
            ->line('We are pleased to let you know that the grant for your transaction'.$this->forBusiness()
                .' (reference **'.$eoi->reference.'**) has been **formally approved** under the CAFI Co-Investment Facility: '
                .'the Grant Agreement has been signed by the CAFI Managing Director and the MTIBD Principal Secretary.');

        if ($a->grant_amount !== null) {
            $mail->line('**Approved CAFI grant:** M '.number_format((float) $a->grant_amount)
                .' (US$ '.number_format((float) $a->grant_amount / max($rate, 0.0001)).')');
        }

        $mail->line('**Next step:** meeting the **conditions precedent** and **onboarding** (Gate 6). '
                .'The LEHSFF team will contact you to take this forward.')
            ->line('Please note: approval does not itself trigger payment. The grant is released in tranches after Gate 6, '
                .'once your investor’s capital has been verified.');

        $open = collect($a->conditions ?? [])->where('status', 'open')->filter(fn ($c) => filled($c['finding'] ?? null))->values();
        if ($open->isNotEmpty()) {
            $mail->line('**Conditions still to be met:**');
            foreach ($open as $i => $c) {
                $when = Authorisation::CONDITION_TYPES[$c['type'] ?? ''][1] ?? null;
                $mail->line(($i + 1).'. **'.$c['finding'].'**'.($when ? ' — '.$when : ''));
            }
        }

        return $mail->action('View my application', $this->applicationUrl());
    }

    protected function notAuthorised(MailMessage $mail): MailMessage
    {
        $eoi = $this->eoi;

        return $mail->subject('Outcome of your application '.$eoi->reference)
            ->line('Thank you for your patience while your transaction'.$this->forBusiness()
                .' (reference **'.$eoi->reference.'**) went through final authorisation.')
            ->line('We regret to inform you that the grant **was not approved** and will not proceed further under the Co-Investment Facility.')
            ->action('View my application', $this->applicationUrl())
            ->line('If you have any questions about this decision, reply to this e-mail or contact the LEHSFF team.');
    }

    protected function forBusiness(): string
    {
        return $this->eoi->business_name ? ' for **'.$this->eoi->business_name.'**' : '';
    }

    protected function applicationUrl(): string
    {
        return Route::has('expression-of-interest.show')
            ? route('expression-of-interest.show', ['id' => $this->eoi->id])
            : url('/');
    }
}