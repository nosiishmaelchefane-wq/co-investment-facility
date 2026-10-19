<?php

namespace App\Notifications;

use App\Models\CommitteeReview;
use App\Models\ExpressionOfInterest;
use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;
use Illuminate\Support\Facades\Route;

/**
 * G3 · Staff e-mail when the Committee outcome is recorded — to the PMU and the Committee members.
 *   Approve / Conditional → prepare Gate 4 (final authorisation); lists the agreed conditions
 *   Defer                 → the actions to complete before resubmitting to the Committee
 *   Reject                → the transaction is closed
 * Includes the approved figures (Maloti with US$) and the rationale (internal).
 */
class EoiCommitteeDecisionRecorded extends Notification
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
        $eoi     = $this->eoi;
        $r       = $this->review;
        $outcome = $r->outcome;
        [$label, , $next] = CommitteeReview::OUTCOMES[$outcome] ?? [$outcome, '', ''];
        $rate    = (float) config('cif.usd_to_lsl', 18.00);
        $money   = fn ($v) => $v === null || $v === ''
            ? '—'
            : 'M '.number_format((float) $v).' (US$ '.number_format((float) $v / max($rate, 0.0001)).')';

        $mail = (new MailMessage)
            ->subject('Committee decision: '.$label.' — '.$eoi->reference.($eoi->business_name ? ' ('.$eoi->business_name.')' : ''))
            ->greeting('Hello '.($notifiable->name ?: '').',')
            ->line('The Investment Committee outcome for **'.($eoi->business_name ?: $eoi->reference).'** (reference **'.$eoi->reference.'**) '
                .'has been recorded: **'.$label.'**.')
            ->line('**Meeting:** '.(collect([$r->meeting_ref, $r->meeting_date?->format('d M Y')])->filter()->implode(' · ') ?: '—'));

        if (in_array($outcome, [CommitteeReview::OUTCOME_APPROVE, CommitteeReview::OUTCOME_CONDITIONAL], true)) {
            $mail->line('**Private capital:** '.$money($r->private_amount))
                 ->line('**CAFI grant:** '.$money($r->grant_amount));

            $conditions = collect($r->conditions ?? [])
                ->filter(fn ($c) => ($c['decision'] ?? '') !== 'removed' && filled($c['finding'] ?? null) && ($c['status'] ?? 'open') !== 'resolved')
                ->values();
            if ($conditions->isNotEmpty()) {
                $mail->line('**Conditions:**');
                foreach ($conditions as $i => $c) {
                    $mail->line(($i + 1).'. **'.$c['finding'].'** — '.ucfirst($c['owner'] ?? 'enterprise')
                        .(filled($c['treatment'] ?? null) ? ' · '.$c['treatment'] : ''));
                }
            }
        }

        if ($outcome === CommitteeReview::OUTCOME_DEFER && filled($r->actions)) {
            $mail->line('**Actions before resubmission:**')
                 ->line('> '.str_replace(["\r\n", "\n"], ' ', $r->actions));
        }

        if (filled($r->rationale)) {
            $mail->line('**Rationale:**')
                 ->line('> '.str_replace(["\r\n", "\n"], ' ', $r->rationale));
        }

        $mail->line('**Next step:** '.$next.'.');

        if (in_array($outcome, [CommitteeReview::OUTCOME_APPROVE, CommitteeReview::OUTCOME_CONDITIONAL], true)) {
            $mail->line('The PMU prepares the authorisation document for signature by the CAFI Managing Director and the MTIBD Principal Secretary (Gate 4).');
        } elseif ($outcome === CommitteeReview::OUTCOME_DEFER) {
            $mail->line('The PMU completes the actions above and resubmits the case to the Committee from the G3 tab.');
        }

        return $mail->action('Open the application', $this->applicationUrl());
    }

    protected function applicationUrl(): string
    {
        return Route::has('expression-of-interest.show')
            ? route('expression-of-interest.show', ['id' => $this->eoi->id])
            : url('/');
    }
}