<?php

namespace App\Notifications;

use App\Models\ExpressionOfInterest;
use App\Models\GrantReadiness;
use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\Str;

/**
 * G4 · Grant readiness review — staff e-mails (the applicant is not e-mailed; Gate 4 is an internal control point).
 *   submitted → Hub Manager:            Grant Readiness Memo + Draft Grant Agreement ready for review
 *   hub       → PMU Director:           approved for submission by the Hub Manager
 *   returned  → Grants Management + PMU: returned by the Hub Manager / PMU Director, with the reason
 *   approved  → PMU + MD/PS signers:     approved to proceed to Gate 5 final authorisation
 */
class EoiGrantReadinessUpdate extends Notification
{
    use Queueable;

    public function __construct(
        public ExpressionOfInterest $eoi,
        public GrantReadiness $gr,
        public string $event,
    ) {}

    public function via(object $notifiable): array
    {
        return ['mail'];
    }

    public function toMail(object $notifiable): MailMessage
    {
        $eoi  = $this->eoi;
        $gr   = $this->gr;
        $name = $eoi->business_name ?: $eoi->reference;
        $rec  = GrantReadiness::RECOMMENDATIONS[$gr->recommendation][0] ?? '—';

        [$subject, $intro, $next] = match ($this->event) {
            'submitted' => [
                'Grant readiness: ready for Hub Manager review',
                'The Grants Management Representative has completed the grant readiness review for **'.$name.'** and recommends: **'.$rec.'**.',
                'Review the Grant Readiness Memo and the Draft Grant Agreement, then **approve for submission** or **return for further action** (G4 tab · step 2).',
            ],
            'hub' => [
                'Grant readiness: approved by the Hub Manager',
                'The Hub Manager has approved the grant readiness file for **'.$name.'** for submission.',
                'As PMU Director of Implementation, confirm the file is ready for final authorisation: **approve to proceed** or **return** (G4 tab · step 3).',
            ],
            'returned' => [
                'Grant readiness: returned for further action',
                'The grant readiness file for **'.$name.'** was returned by the **'.(GrantReadiness::STAGES[$gr->returnedBy()][1] ?? 'reviewer').'**.',
                'Resolve the matters raised and resubmit to the Hub Manager from the G4 tab — a new round has been opened with everything carried over.',
            ],
            'approved' => [
                'Grant readiness: approved to proceed to final authorisation',
                'The PMU Director of Implementation has approved **'.$name.'** to proceed to **Gate 5 · Final authorisation**.',
                'Send the Grant Agreement, with the Grant Readiness Memo, to the **CAFI Managing Director** for signature, then to the **MTIBD Principal Secretary** (G5 tab).',
            ],
            default => ['Grant readiness update', 'There is an update on '.$name.'.', 'See the G4 tab.'],
        };

        $mail = (new MailMessage)
            ->subject($subject.' — '.$eoi->reference.($eoi->business_name ? ' ('.$eoi->business_name.')' : ''))
            ->greeting('Hello '.($notifiable->name ?? '').',')
            ->line($intro)
            ->line('**Private investment:** '.$this->money($gr->private_amount))
            ->line('**Proposed CAFI grant:** '.$this->money($gr->grant_amount))
            ->line('**Recommendation:** '.$rec.($gr->justification ? ' — '.Str::limit(trim(preg_replace('/\s+/', ' ', $gr->justification)), 300) : ''));

        if ($this->event === 'returned' && filled($gr->returnComment())) {
            $mail->line('**Reason:** '.Str::limit(trim(preg_replace('/\s+/', ' ', $gr->returnComment())), 600));
        }

        $open = collect($gr->issues ?? [])->where('status', '!=', 'resolved');
        if ($open->isNotEmpty()) {
            $mail->line('**Outstanding issues ('.$open->count().'):**');
            foreach ($open->take(6) as $i) {
                $mail->line('- '.Str::limit($i['issue'] ?? '', 160).' *('.(GrantReadiness::PARTIES[$i['party'] ?? ''] ?? '—').')*');
            }
        }

        return $mail
            ->line('**Next step:** '.$next)
            ->action('Open the grant readiness review', $this->url())
            ->line('Gate 4 confirms readiness for final authorisation. It is not grant authorisation and does not approve any payment.');
    }

    protected function money($lsl): string
    {
        $rate = max((float) config('cif.usd_to_lsl', 18.00), 0.0001);

        return $lsl === null || $lsl === '' ? '—' : 'M '.number_format((float) $lsl).' (US$ '.number_format((float) $lsl / $rate).')';
    }

    protected function url(): string
    {
        return Route::has('expression-of-interest.show')
            ? route('expression-of-interest.show', ['id' => $this->eoi->id]).'?tab='.($this->event === 'approved' ? 'g4' : 'readiness')
            : url('/');
    }
}