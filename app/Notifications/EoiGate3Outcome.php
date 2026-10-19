<?php

namespace App\Notifications;

use App\Models\CommitteeReview;
use App\Models\ExpressionOfInterest;
use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;
use Illuminate\Support\Facades\Route;

/**
 * G3 · Investment Committee outcome e-mailed to the applicant (matches what they see on the G3 tab):
 *   Approve / Conditional → recommended, goes to final authorisation (+ the conditions their enterprise owns)
 *   Defer                 → more information needed, the LEHSFF team will be in touch
 *   Reject                → will not proceed, with the Committee's rationale
 */
class EoiGate3Outcome extends Notification
{
    use Queueable;

    public function __construct(
        public ExpressionOfInterest $eoi,
        public CommitteeReview $review,
    ) {}

    public function via(object $notifiable): array
    {
        return ['mail'];
    }

    public function toMail(object $notifiable): MailMessage
    {
        $mail = (new MailMessage)->greeting('Hello '.($notifiable->name ?: '').',');

        return match ($this->review->outcome) {
            CommitteeReview::OUTCOME_APPROVE, CommitteeReview::OUTCOME_CONDITIONAL => $this->recommended($mail),
            CommitteeReview::OUTCOME_DEFER                                         => $this->deferred($mail),
            default                                                                => $this->rejected($mail),
        };
    }

    /* ----------------------------------------------------------------- */

    protected function recommended(MailMessage $mail): MailMessage
    {
        $eoi         = $this->eoi;
        $conditional = $this->review->outcome === CommitteeReview::OUTCOME_CONDITIONAL;

        $mail->subject('Good news: the Investment Committee has recommended '.$eoi->reference)
            ->line('The Investment Committee has reviewed your application'.$this->forBusiness()
                .' (reference **'.$eoi->reference.'**) and has **recommended it'.($conditional ? ', subject to conditions' : '').'**.')
            ->line('It now goes to **final authorisation** by the CAFI Managing Director (and, where applicable, the MTIBD Principal Secretary).')
            ->line('Please note: the Committee’s recommendation is not yet a grant approval. You will be notified once final authorisation is given.');

        $mine = $this->enterpriseConditions();
        if ($mine) {
            $mail->line('**Conditions for your enterprise:**');
            foreach ($mine as $i => $c) {
                $mail->line(($i + 1).'. **'.$c['finding'].'**'.(filled($c['treatment'] ?? null) ? ' — '.$c['treatment'] : ''));
            }
            $mail->line('You can start preparing these now — the LEHSFF team will confirm when each one is needed.');
        }

        return $mail->action('View my application', $this->applicationUrl());
    }

    protected function deferred(MailMessage $mail): MailMessage
    {
        $eoi = $this->eoi;

        return $mail->subject('Update on your application '.$eoi->reference)
            ->line('The Investment Committee has reviewed your application'.$this->forBusiness()
                .' (reference **'.$eoi->reference.'**).')
            ->line('The Committee needs **more information** before it can make a recommendation, so a decision has been **deferred**.')
            ->line('The LEHSFF team will contact you about what is needed. Once it has been provided, your application will go back to the Committee.')
            ->action('View my application', $this->applicationUrl())
            ->line('If you have any questions, reply to this e-mail or contact the LEHSFF team.');
    }

    protected function rejected(MailMessage $mail): MailMessage
    {
        $eoi = $this->eoi;

        $mail->subject('Outcome of your application '.$eoi->reference)
            ->line('Thank you for your patience while the Investment Committee reviewed your application'.$this->forBusiness()
                .' (reference **'.$eoi->reference.'**).')
            ->line('We regret to inform you that the Committee decided that the transaction **should not proceed** under the Co-Investment Facility.');

        if (filled($this->review->rationale)) {
            $mail->line('**The Committee’s reasons:**')
                 ->line('> '.str_replace(["\r\n", "\n"], ' ', $this->review->rationale));
        }

        return $mail
            ->action('View my application', $this->applicationUrl())
            ->line('This decision does not stop you from applying again in future. The LEHSFF team and the wider ecosystem can support you in the meantime.')
            ->line('If you have any questions about this decision, reply to this e-mail or contact the LEHSFF team.');
    }

    /* ----------------------------------------------------------------- */

    /** Open conditions owned by the enterprise (same filter as the applicant's G3 tab) */
    protected function enterpriseConditions(): array
    {
        return collect($this->review->conditions ?? [])
            ->filter(fn ($c) => ($c['decision'] ?? '') !== 'removed'
                && ($c['owner'] ?? 'enterprise') === 'enterprise'
                && ($c['status'] ?? 'open') !== 'resolved'
                && filled($c['finding'] ?? null))
            ->values()->all();
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