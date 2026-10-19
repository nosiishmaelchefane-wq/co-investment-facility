<?php

namespace App\Notifications\Gate5;

use App\Models\ConditionReview;
use App\Models\ExpressionOfInterest;
use Illuminate\Notifications\Messages\MailMessage;

/**
 * G5 · Staff alerts.
 *   evidence_uploaded  → PMU: the enterprise uploaded evidence for a condition (verify it)
 *   capital_recorded   → PMU: the enterprise recorded an investor capital deployment (verify it)
 *   cleared            → Finance + PMU: cleared for disbursement — tranches can be released
 */
class Gate5StaffAlert extends Gate5Mail
{
    public function __construct(
        ExpressionOfInterest $eoi,
        ConditionReview $review,
        public string $event,
        public array $data = [],
    ) {
        parent::__construct($eoi, $review);
    }

    public function toMail(object $notifiable): MailMessage
    {
        $name = $this->eoi->business_name ?: $this->eoi->reference;
        $tag  = $this->eoi->reference.($this->eoi->business_name ? ' ('.$this->eoi->business_name.')' : '');

        $mail = match ($this->event) {
            'evidence_uploaded' => $this->start($notifiable, 'Evidence to verify: '.$tag)
                ->line('**'.$name.'** uploaded evidence for a condition precedent ('.$this->ref().'):')
                ->line($this->quote($this->data['condition'] ?? ''))
                ->line('**File:** '.($this->data['file'] ?? '—'))
                ->line('Check it on the G5 tab and mark the condition **Met** (or ask for something else).'),

            'capital_recorded' => $this->start($notifiable, 'Investor capital to verify: '.$tag)
                ->line('**'.$name.'** recorded an investor capital deployment ('.$this->ref().'):')
                ->line('**'.($this->data['investor'] ?? '—').'** — '.$this->money($this->data['amount'] ?? null)
                    .(isset($this->data['on']) ? ' · deployed '.$this->data['on'] : ''))
                ->line('Check the evidence on the G5 tab and **verify** it, or mark it not accepted with a reason.'),

            'cleared' => $this->start($notifiable, 'Cleared for disbursement: '.$tag)
                ->line('**'.$name.'** ('.$this->ref().') has been **cleared for disbursement** at Gate 5.')
                ->line('**Approved CAFI grant:** '.$this->money($this->review->grant_amount)),

            default => $this->start($notifiable, 'Gate 5 update: '.$tag)->line('There is an update on '.$this->ref().'.'),
        };

        if ($this->event === 'cleared') {
            $tranches = $this->review->tranches()->orderBy('position')->get();
            if ($tranches->isNotEmpty()) {
                $mail->line('**Tranches (released one by one, in order):**');
                foreach ($tranches as $t) {
                    $mail->line('- **'.$t->label.'** — '.$this->money($t->amount).($t->trigger ? ' · '.$t->trigger : ''));
                }
            }
            $unplanned = max(0, (float) $this->review->grant_amount - (float) $tranches->sum('amount'));
            if ($unplanned > 0.01) {
                $mail->line('**Not yet in a tranche:** '.$this->money($unplanned).' — add the next tranche on the G5 tab when it is due.');
            }
            $mail->line('Finance: release the tranches one by one on the G5 tab with the payment reference, date and proof of payment.');
        }

        return $mail->action('Open the application', $this->url());
    }
}