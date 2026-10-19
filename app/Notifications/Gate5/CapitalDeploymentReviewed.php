<?php

namespace App\Notifications\Gate5;

use App\Models\CapitalDeployment;
use App\Models\ConditionReview;
use App\Models\ExpressionOfInterest;
use Illuminate\Notifications\Messages\MailMessage;

/**
 * G5 · The PMU verified or did not accept an investor capital deployment the enterprise recorded — to the applicant.
 */
class CapitalDeploymentReviewed extends Gate5Mail
{
    public function __construct(
        ExpressionOfInterest $eoi,
        ConditionReview $review,
        public CapitalDeployment $deployment,
    ) {
        parent::__construct($eoi, $review);
    }

    public function toMail(object $notifiable): MailMessage
    {
        $d        = $this->deployment;
        $verified = $d->status === 'verified';
        $line     = $this->money($d->amount).' from **'.$d->investor.'** (deployed '.$d->deployed_on?->format('d M Y').')';

        $mail = $this->start($notifiable, ($verified ? 'Investor capital verified: ' : 'Investor capital not accepted: ').$this->eoi->reference)
            ->line($verified
                ? 'The LEHSFF team has **verified** the investor capital you recorded for '.$this->ref().': '.$line.'.'
                : 'The LEHSFF team **could not accept** the investor capital you recorded for '.$this->ref().': '.$line.'.');

        if (! $verified && filled($d->note)) {
            $mail->line('**Reason:**')->line($this->quote($d->note));
            $mail->line('Please record it again with the correct amount or better evidence (bank confirmation or transfer receipt).');
        }

        if ($verified) {
            $mail->line('**Verified so far:** '.$this->money($this->review->verifiedCapital()).' of '.$this->money($this->review->private_amount).' committed.');
        }

        return $mail->action('View my application', $this->url());
    }
}