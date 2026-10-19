<?php

namespace App\Notifications\Gate5;

use App\Models\ConditionReview;
use App\Models\DisbursementTranche;
use App\Models\ExpressionOfInterest;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Support\Facades\Storage;

/**
 * G5 · Finance released a tranche — to the applicant and the PMU.
 */
class TrancheReleased extends Gate5Mail
{
    public function __construct(
        ExpressionOfInterest $eoi,
        ConditionReview $review,
        public DisbursementTranche $tranche,
    ) {
        parent::__construct($eoi, $review);
    }

    public function toMail(object $notifiable): MailMessage
    {
        $t        = $this->tranche;
        $all      = $this->review->tranches()->orderBy('position')->get();
        $released = (float) $all->whereNotNull('released_on')->sum('amount');
        $left     = max(0, (float) $this->review->grant_amount - $released);
        $next     = $all->whereNull('released_on')->first();

        $mail = $this->start($notifiable, 'Tranche '.$t->label.' released: '.$this->eoi->reference)
            ->line('Tranche **'.$t->label.'** of the CAFI grant for'.($this->eoi->business_name ? ' **'.$this->eoi->business_name.'**' : '').' ('.$this->ref().') has been released.')
            ->line('**Amount:** '.$this->money($t->amount))
            ->line('**Release date:** '.$t->released_on?->format('d M Y').' · **Payment reference:** '.($t->reference ?: '—'))
            ->line('**Released so far:** '.$this->money($released).' of '.$this->money($this->review->grant_amount))
            ->line('**Still to be disbursed:** '.$this->money($left));

        if ($left <= 0.01) {
            $mail->line('This was the **final tranche** — the full CAFI grant has now been disbursed.');
        } elseif ($next) {
            $mail->line('**Next tranche:** '.$next->label.' — '.$this->money($next->amount).($next->trigger ? ' · on '.$next->trigger : ''));
        } else {
            $mail->line('The remaining '.$this->money($left).' will be released in further tranches as agreed with the LEHSFF team.');
        }

        // Proof of payment: attached when small enough for e-mail, otherwise available on the G5 tab
        $disk = Storage::disk(ConditionReview::DISK);
        if ($t->proof_path && $disk->exists($t->proof_path)) {
            if ($disk->size($t->proof_path) <= 8 * 1024 * 1024) {
                $mail->attachData($disk->get($t->proof_path), $t->proof_name ?: 'proof-of-payment.pdf', ['mime' => $disk->mimeType($t->proof_path) ?: 'application/octet-stream']);
                $mail->line('The **proof of payment** is attached.');
            } else {
                $mail->line('The **proof of payment** can be downloaded on the G5 tab.');
            }
        }

        return $mail->action('View the application', $this->url())
            ->line('Please use the grant as set out in the approved grant utilisation budget and report as agreed.');
    }
}