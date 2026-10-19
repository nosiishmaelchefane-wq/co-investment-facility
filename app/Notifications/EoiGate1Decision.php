<?php

namespace App\Notifications;

use App\Models\EligibilityScreening;
use App\Models\ExpressionOfInterest;
use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;
use Illuminate\Support\Facades\Route;

/**
 * G1 · Gate 1 decision e-mailed to the applicant:
 *   Pass                   → progresses to Due Diligence and Investment Appraisal
 *   Clarification required → returned, with the information requirements
 *   Not eligible           → does not progress, with the basis for the decision
 * (The completeness return is sent by EoiReturnedForCorrection.)
 * Uses Laravel's default mail template (resources/views/vendor/mail — logo in the header).
 */
class EoiGate1Decision extends Notification
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
        $mail = (new MailMessage)->greeting('Hello '.($notifiable->name ?: '').',');

        return match ($this->screening->decision) {
            EligibilityScreening::DECISION_ELIGIBLE      => $this->passed($mail),
            EligibilityScreening::DECISION_CLARIFICATION => $this->clarification($mail),
            default                                      => $this->notEligible($mail),
        };
    }

    /* ----------------------------------------------------------------- */

    protected function passed(MailMessage $mail): MailMessage
    {
        $eoi = $this->eoi;

        $mail->subject('Good news: '.$eoi->reference.' has passed eligibility screening')
            ->line('We are pleased to let you know that your Transaction Expression of Interest'.$this->forBusiness()
                .' (reference **'.$eoi->reference.'**) has **passed eligibility screening** (Gate 1).')
            ->line('Your application now moves on to **Due Diligence and Investment Appraisal**. '
                .'The LEHSFF team will contact you about the next steps and any further documents needed.')
            ->line('Please note: passing Gate 1 means the transaction meets the Facility’s basic eligibility requirements. '
                .'It is not yet approval of the co-investment grant.');

        $this->addComments($mail, 'Comments from the LEHSFF team');

        return $mail
            ->action('View my application', $this->applicationUrl())
            ->line('Keep your enterprise profile and documents up to date — they will be used in the next stage.');
    }

    protected function clarification(MailMessage $mail): MailMessage
    {
        $eoi   = $this->eoi;
        $items = $this->screening->outstandingItems();

        $mail->subject('Action needed: more information required for '.$eoi->reference)
            ->line('Thank you for your Transaction Expression of Interest'.$this->forBusiness()
                .' (reference **'.$eoi->reference.'**).')
            ->line('The LEHSFF team has screened it against the Facility’s eligibility criteria and needs **more information** '
                .'before a decision can be made. Your application has been returned to you so you can respond.');

        if ($items) {
            $mail->line('**Information required:**');
            $this->addItems($mail, $items);
        }

        $this->addComments($mail, 'Message from the LEHSFF team');

        return $mail
            ->action('Update my application', $this->applicationUrl())
            ->line('Once you have updated your EOI and enterprise profile, resubmit the application. It will then be screened again.')
            ->line('If you have any questions, reply to this e-mail or contact the LEHSFF team.');
    }

    protected function notEligible(MailMessage $mail): MailMessage
    {
        $eoi   = $this->eoi;
        $items = $this->screening->failedItems();

        $mail->subject('Outcome of your application '.$eoi->reference)
            ->line('Thank you for your interest in the CAFI Co-Investment Facility and for submitting your Transaction Expression of Interest'
                .$this->forBusiness().' (reference **'.$eoi->reference.'**).')
            ->line('After screening it against the Facility’s eligibility criteria, we regret to inform you that the application '
                .'**does not meet the eligibility requirements** and will not progress further.');

        if ($items) {
            $mail->line('**Basis for the decision:**');
            $this->addItems($mail, $items);
        }

        $this->addComments($mail, 'Comments from the LEHSFF team');

        return $mail
            ->action('View my application', $this->applicationUrl())
            ->line('This decision does not stop you from applying again in future once your business meets the criteria. '
                .'The LEHSFF team and the wider ecosystem can also help you become investment-ready.')
            ->line('If you have any questions about this decision, reply to this e-mail or contact the LEHSFF team.');
    }

    /* ----------------------------------------------------------------- */

    protected function forBusiness(): string
    {
        return $this->eoi->business_name ? ' for **'.$this->eoi->business_name.'**' : '';
    }

    /** Numbered list: title — officer's note */
    protected function addItems(MailMessage $mail, array $items): void
    {
        foreach ($items as $i => $item) {
            $mail->line(($i + 1).'. **'.$item['title'].'**'.(filled($item['note']) ? ' — '.$item['note'] : ''));
        }
    }

    protected function addComments(MailMessage $mail, string $heading): void
    {
        if (filled($this->screening->comments)) {
            $mail->line('**'.$heading.':**')
                 ->line('> '.str_replace(["\r\n", "\n"], ' ', $this->screening->comments));
        }
    }

    /** Link to the application page (falls back to the site root if the route is missing) */
    protected function applicationUrl(): string
    {
        return Route::has('expression-of-interest.show')
            ? route('expression-of-interest.show', ['id' => $this->eoi->id])
            : url('/');
    }
}