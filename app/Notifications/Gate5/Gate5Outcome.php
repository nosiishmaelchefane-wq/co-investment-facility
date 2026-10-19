<?php

namespace App\Notifications\Gate5;

use App\Models\ConditionReview;
use Illuminate\Notifications\Messages\MailMessage;

/**
 * G5 · Status recorded — to the applicant.
 *   Cleared for disbursement · Pending conditions · Pending investor deployment · Not satisfied
 * The PMU's comments are included (they are also shown to the applicant on the G5 tab).
 */
class Gate5Outcome extends Gate5Mail
{
    public function toMail(object $notifiable): MailMessage
    {
        $r       = $this->review;
        $outcome = $r->outcome;

        $mail = match ($outcome) {
            ConditionReview::OUTCOME_CLEARED => $this->start($notifiable, 'Cleared for disbursement: '.$this->eoi->reference)
                ->line('Good news — your transaction'.$this->forBusiness().' ('.$this->ref().') has been **cleared for disbursement**.')
                ->line('The CAFI grant of '.$this->money($r->grant_amount).' will be paid in tranches. You will receive an e-mail each time a tranche is released.'),

            ConditionReview::OUTCOME_PENDING_INVESTOR => $this->start($notifiable, 'Action needed: investor capital for '.$this->eoi->reference)
                ->line('Your transaction'.$this->forBusiness().' ('.$this->ref().') is **waiting for your investor’s capital to be deployed and verified**.')
                ->line('No CAFI grant can be paid before then. Record each deployment with its evidence on the G5 tab.'),

            ConditionReview::OUTCOME_NOT_SATISFIED => $this->start($notifiable, 'Update on your transaction '.$this->eoi->reference)
                ->line('Your transaction'.$this->forBusiness().' ('.$this->ref().') is **under review** because a material condition cannot be met or circumstances have changed.')
                ->line('The LEHSFF team will contact you about the next steps.'),

            default => $this->start($notifiable, 'Conditions outstanding: '.$this->eoi->reference)
                ->line('Your transaction'.$this->forBusiness().' ('.$this->ref().') is **waiting for outstanding conditions** before the CAFI grant can be paid.'),
        };

        // Outstanding conditions (not for "cleared")
        if ($outcome !== ConditionReview::OUTCOME_CLEARED) {
            $open = $r->conditions()->where('status', 'outstanding')->orderBy('position')->get();
            if ($open->isNotEmpty()) {
                $mail->line('**Outstanding conditions:**');
                foreach ($open as $i => $c) {
                    $mail->line(($i + 1).'. '.$c->finding);
                }
                $mail->line('Upload evidence for each one on the G5 tab — the LEHSFF team will verify it.');
            }
        }

        if (filled($r->outcome_comments)) {
            $mail->line('**Message from the LEHSFF team:**')->line($this->quote($r->outcome_comments));
        }

        return $mail->action('View my application', $this->url());
    }
}