<?php

namespace App\Notifications\Gate5;

use App\Models\ConditionReview;
use App\Models\ExpressionOfInterest;
use Illuminate\Notifications\Messages\MailMessage;

/**
 * G6 · Staff alerts.
 *   evidence_uploaded  → PMU: the enterprise uploaded evidence for a condition (verify it)
 *   capital_recorded   → PMU: the enterprise recorded an investor capital deployment (verify it)
 *   cleared             → Finance + PMU: cleared for disbursement — Gate 7 payment processing
 *   readiness_requested → Finance: programme confirmed readiness — record the Finance confirmation
 *   payment_requested   → payment approvers (disburse.approve): matching investment verified — approve the payment
 *   payment_approved    → Finance: payment approved — instruction + confirmation of payment
 *   payment_stopped     → PMU + approvers: not eligible for disbursement — escalation
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
                ->line('Check it on the G6 tab and mark the condition **Met** (or ask for something else).'),

            'capital_recorded' => $this->start($notifiable, 'Investor capital to verify: '.$tag)
                ->line('**'.$name.'** recorded an investor capital deployment ('.$this->ref().'):')
                ->line('**'.($this->data['investor'] ?? '—').'** — '.$this->money($this->data['amount'] ?? null)
                    .(isset($this->data['on']) ? ' · deployed '.$this->data['on'] : ''))
                ->line('Check the evidence on the G6 tab and **verify** it, or mark it not accepted with a reason.'),

            'cleared' => $this->start($notifiable, 'Cleared for disbursement: '.$tag)
                ->line('**'.$name.'** ('.$this->ref().') has been **cleared for disbursement** at Gate 6.')
                ->line('**Approved CAFI grant:** '.$this->money($this->review->grant_amount)),

            'readiness_requested' => $this->start($notifiable, 'Confirm disbursement readiness: '.$tag)
                ->line('The programme team has verified the conditions precedent for **'.$name.'** ('.$this->ref().') and confirmed readiness for disbursement.')
                ->line('**Approved CAFI grant:** '.$this->money($this->review->grant_amount))
                ->line('Finance: review the disbursement file (executed agreement, budget, verified bank account, investment evidence) and record the **Finance confirmation** on the G6 tab.'),

            'payment_requested' => $this->start($notifiable, 'Payment approval needed: '.$tag)
                ->line('A grant payment for **'.$name.'** ('.$this->ref().') is ready for approval.')
                ->line('**Tranche:** '.($this->data['tranche'] ?? '—').' — '.$this->money($this->data['amount'] ?? null))
                ->line('**Private capital verified:** '.$this->money($this->data['capital'] ?? null).' · **Grant allowed by the matching rule:** '.$this->money($this->data['allowed'] ?? null))
                ->line('Check the matching calculation and controls on the G6 tab, then **approve the payment** or hold / stop it.'),

            'payment_approved' => $this->start($notifiable, 'Payment approved — please process: '.$tag)
                ->line('Payment of tranche **'.($this->data['tranche'] ?? '—').'** ('.$this->money($this->data['amount'] ?? null).') for **'.$name.'** ('.$this->ref().') has been **approved**.')
                ->line('Finance: issue the payment instruction to the verified bank account and record the confirmation of payment with proof on the G6 tab.'),

            'payment_stopped' => $this->start($notifiable, 'Payment not eligible — escalation: '.$tag)
                ->line('A grant payment for **'.$name.'** ('.$this->ref().') was marked **not eligible for disbursement**.')
                ->line($this->quote($this->data['note'] ?? ''))
                ->line('The payment is stopped and the transaction is escalated under the programme’s governance and financial controls.'),

            default => $this->start($notifiable, 'Gate 6 update: '.$tag)->line('There is an update on '.$this->ref().'.'),
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
                $mail->line('**Not yet in a tranche:** '.$this->money($unplanned).' — add the next tranche on the G6 tab when it is due.');
            }
            $mail->line('Gate 7: for each tranche the PMU verifies the matching investment and requests payment, the payment is approved, '
                .'then Finance issues the instruction and records the confirmation of payment with proof.');
        }

        return $mail->action('Open the application', $this->url());
    }
}