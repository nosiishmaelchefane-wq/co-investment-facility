<?php

namespace App\Notifications;

use App\Models\DueDiligence;
use App\Models\DueDiligenceCondition;
use App\Models\ExpressionOfInterest;
use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;
use Illuminate\Support\Facades\Route;

/**
 * G2 · Due diligence outcome e-mailed to the applicant:
 *   Proceed                → goes to the Investment Committee (+ conditions to prepare for later)
 *   Clarification required → on hold, with the actions for the enterprise / investor
 *   Remediation required   → on hold, with the corrective actions
 *   Do not progress        → stops, with the basis for the decision (the assessor's conclusion)
 * Uses Laravel's default mail template (resources/views/vendor/mail — logo in the header).
 */
class EoiGate2Outcome extends Notification
{
    use Queueable;

    public function __construct(
        public ExpressionOfInterest $eoi,
        public DueDiligence $dd,
    ) {}

    public function via(object $notifiable): array
    {
        return ['mail'];
    }

    public function toMail(object $notifiable): MailMessage
    {
        $mail = (new MailMessage)->greeting('Hello '.($notifiable->name ?: '').',');

        return match ($this->dd->outcome) {
            DueDiligence::OUTCOME_PROCEED       => $this->proceed($mail),
            DueDiligence::OUTCOME_CLARIFICATION => $this->onHold($mail, remediation: false),
            DueDiligence::OUTCOME_REMEDIATION   => $this->onHold($mail, remediation: true),
            default                             => $this->doNotProgress($mail),
        };
    }

    /* ----------------------------------------------------------------- */

    protected function proceed(MailMessage $mail): MailMessage
    {
        $eoi = $this->eoi;

        $mail->subject('Good news: '.$eoi->reference.' has completed due diligence')
            ->line('Due diligence and investment appraisal of your application'.$this->forBusiness()
                .' (reference **'.$eoi->reference.'**) is complete.')
            ->line('Your application will now be presented to the **Investment Committee** for evaluation (Gate 3). '
                .'You will be notified of the Committee’s decision.')
            ->line('Please note: this is not yet approval of the co-investment grant.');

        // Later conditions the enterprise / investor should start preparing (not blocking now)
        $later = $this->openConditions(fn ($c) => $c->treatment !== 'before_ic');
        if ($later) {
            $mail->line('**Conditions to prepare for if the investment is approved:**');
            $this->addConditions($mail, $later, withTiming: true);
        }

        return $mail
            ->action('View my application', $this->applicationUrl())
            ->line('Keep your enterprise profile and documents up to date — the Committee will use them.');
    }

    protected function onHold(MailMessage $mail, bool $remediation): MailMessage
    {
        $eoi   = $this->eoi;
        $items = $this->openConditions();

        $mail->subject('Action needed: '.($remediation ? 'corrective action' : 'more information').' required for '.$eoi->reference)
            ->line('The LEHSFF team has been carrying out due diligence on your application'.$this->forBusiness()
                .' (reference **'.$eoi->reference.'**).')
            ->line($remediation
                ? 'The transaction looks viable, but **a corrective action is needed** before it can go to the Investment Committee.'
                : '**More information or evidence is needed** before the assessment can be concluded.')
            ->line('Your application is on hold until this is received.');

        if ($items) {
            $mail->line('**What is required:**');
            $this->addConditions($mail, $items, withTiming: false);
        }

        return $mail
            ->action('View my application', $this->applicationUrl())
            ->line('Upload any documents to your enterprise profile, or send them to the LEHSFF team. '
                .'Once everything is received, the assessment will resume.')
            ->line('If you have any questions, reply to this e-mail or contact the LEHSFF team.');
    }

    protected function doNotProgress(MailMessage $mail): MailMessage
    {
        $eoi = $this->eoi;

        $mail->subject('Outcome of your application '.$eoi->reference)
            ->line('Thank you for your patience while the LEHSFF team carried out due diligence on your application'
                .$this->forBusiness().' (reference **'.$eoi->reference.'**).')
            ->line('We regret to inform you that the assessment found an issue that prevents further consideration, '
                .'and the application **will not progress** to the Investment Committee.');

        if (filled($this->dd->conclusion)) {
            $mail->line('**Basis for the decision:**')
                 ->line('> '.str_replace(["\r\n", "\n"], ' ', $this->dd->conclusion));
        }

        return $mail
            ->action('View my application', $this->applicationUrl())
            ->line('This decision does not stop you from applying again in future. '
                .'The LEHSFF team and the wider ecosystem can help you address the issue and become investment-ready.')
            ->line('If you have any questions about this decision, reply to this e-mail or contact the LEHSFF team.');
    }

    /* ----------------------------------------------------------------- */

    /** Open conditions the enterprise or its investor must act on */
    protected function openConditions(?callable $filter = null): array
    {
        return $this->dd->conditions()
            ->where('status', 'open')
            ->whereIn('owner', ['enterprise', 'investor'])
            ->orderBy('position')
            ->get()
            ->when($filter, fn ($c) => $c->filter($filter))
            ->values()
            ->all();
    }

    protected function addConditions(MailMessage $mail, array $conditions, bool $withTiming): void
    {
        foreach ($conditions as $i => $c) {
            $extra = collect([
                $c->owner === 'investor' ? 'with your investor' : null,
                $withTiming ? (DueDiligenceCondition::TREATMENTS[$c->treatment][0] ?? null) : null,
            ])->filter()->implode(' · ');

            $mail->line(($i + 1).'. **'.$c->finding.'**'.($extra ? ' — '.$extra : ''));
        }
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