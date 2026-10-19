<?php

namespace App\Notifications\Gate5;

use App\Models\ConditionReview;
use Illuminate\Notifications\Messages\MailMessage;

/**
 * G5 · Onboarding — the summary of obligations sent to the enterprise (§3.6.5):
 *   contact person, reporting frequency, first report, the arrangements agreed, outstanding conditions and the tranche schedule.
 */
class ObligationsSummary extends Gate5Mail
{
    public function toMail(object $notifiable): MailMessage
    {
        $r = $this->review;
        $o = (array) ($r->onboarding ?? []);

        $mail = $this->start($notifiable, 'Your obligations under the CAFI Co-Investment Facility — '.$this->eoi->reference)
            ->line('Thank you for completing onboarding for your transaction'.$this->forBusiness().' ('.$this->ref().'). '
                .'This is a summary of what was agreed for implementation and monitoring. Please keep it for your records.')
            ->line('**Approved CAFI grant:** '.$this->money($r->grant_amount))
            ->line('**Your contact person:** '.(($o['contact'] ?? null) ?: '—'))
            ->line('**Reporting:** '.(ConditionReview::FREQUENCIES[$o['frequency'] ?? ''] ?? '—')
                .(! empty($o['first_report_on']) ? ' — first report due '.\Illuminate\Support\Carbon::parse($o['first_report_on'])->format('d M Y') : ''));

        $agreed = collect(ConditionReview::ONBOARDING_ITEMS)->filter(fn ($l, $k) => ! empty($o['items'][$k] ?? null))->values();
        if ($agreed->isNotEmpty()) {
            $mail->line('**Arrangements agreed:**');
            foreach ($agreed as $i => $label) {
                $mail->line(($i + 1).'. '.$label);
            }
        }

        $tranches = $r->tranches()->orderBy('position')->get();
        if ($tranches->isNotEmpty()) {
            $mail->line('**Disbursement schedule:**');
            foreach ($tranches as $t) {
                $mail->line('- **'.$t->label.'** — '.$this->money($t->amount).($t->trigger ? ' · '.$t->trigger : ''));
            }
            $rest = max(0, (float) $r->grant_amount - (float) $tranches->sum('amount'));
            if ($rest > 0.01) {
                $mail->line('The remaining '.$this->money($rest).' of the grant will be scheduled in further tranches.');
            }
        }

        $open = $r->conditions()->where('status', 'outstanding')->orderBy('position')->get();
        if ($open->isNotEmpty()) {
            $mail->line('**Conditions still outstanding:**');
            foreach ($open as $i => $c) {
                $mail->line(($i + 1).'. '.$c->finding);
            }
        }

        return $mail->action('View my application', $this->url())
            ->line('If anything in this summary is not what was agreed, reply to this e-mail or contact the LEHSFF team.');
    }
}