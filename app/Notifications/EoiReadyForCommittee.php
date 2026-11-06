<?php

namespace App\Notifications;

use App\Models\DueDiligence;
use App\Models\DueDiligenceCondition;
use App\Models\EnterpriseProfile;
use App\Models\ExpressionOfInterest;
use App\Support\MemorandumPdf;
use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\Str;

/**
 * G2 → G3 · Sent to Investment Committee members (and the Committee secretary) when due diligence is concluded
 * with "Proceed", and again when a deferred case is resubmitted (new round).
 *
 * A briefing on the applicant, so members can prepare before opening the platform:
 *   enterprise · transaction & co-investment (Maloti with US$) · due diligence checklist · the five appraisal
 *   questions of the Investment Appraisal Memorandum · expected outcomes & key risks · open conditions · PMU conclusion
 * The memorandum PDF is attached (skipped quietly if it can't be generated — the link still works).
 */
class EoiReadyForCommittee extends Notification
{
    use Queueable;

    /** PDF bytes, built once and reused for every recipient */
    protected ?string $pdf = null;

    protected bool $pdfTried = false;

    /**
     * $round / $resubmissionNote: set when a deferred case is resubmitted to the Committee (G3 → new round).
     */
    public function __construct(
        public ExpressionOfInterest $eoi,
        public DueDiligence $dd,
        public int $round = 1,
        public ?string $resubmissionNote = null,
    ) {}

    public function via(object $notifiable): array
    {
        return ['mail'];
    }

    public function toMail(object $notifiable): MailMessage
    {
        $eoi  = $this->eoi;
        $dd   = $this->dd->loadMissing('conditions');
        $p    = EnterpriseProfile::where('user_id', $eoi->user_id)->first();
        $form = $dd->toForm();
        $v    = DueDiligence::validated($form);
        $name = $p?->registered_name ?: ($eoi->business_name ?: $eoi->reference);

        $resubmitted = $this->round > 1;

        $mail = (new MailMessage)
            ->subject(($resubmitted ? 'Resubmitted for Committee review (round '.$this->round.'): ' : 'For Committee review: ')
                .$eoi->reference.($eoi->business_name ? ' — '.$eoi->business_name : ''))
            ->greeting('Hello '.($notifiable->name ?? '').',')
            ->line($resubmitted
                ? 'The deferred case **'.$name.'** (reference **'.$eoi->reference.'**) has been resubmitted to the **Investment Committee** as round '
                    .$this->round.'. A new scorecard is needed from each member.'
                : 'Due diligence on **'.$name.'** (reference **'.$eoi->reference.'**) is complete and the PMU recommends that it '
                    .'**proceeds to the Investment Committee** (Gate 3). Below is a summary to help you prepare.');

        if ($resubmitted && filled($this->resubmissionNote)) {
            $mail->line('**How the Committee’s requests were addressed:** '.$this->flat($this->resubmissionNote));
        }

        /* ---------------- Enterprise ---------------- */
        $mail->line('---')->line('### The enterprise');
        $this->facts($mail, [
            'Enterprise'          => $name.($p?->short_name ? ' ('.$p->short_name.')' : ''),
            'Sector'              => collect([$p?->sectorLabel(), $p?->sub_sector])->filter()->implode(' · ') ?: null,
            'Location'            => collect([$p?->city, $p?->district ?: $eoi->district])->filter()->implode(', ') ?: null,
            'Operating since'     => collect([$p?->commencedLabel() ?: $eoi->year_commenced, EnterpriseProfile::STAGES[$p?->company_stage ?? ''] ?? null])->filter()->implode(' · ') ?: null,
            'Business model(s)'   => $p?->businessModelLabel(),
            'OBFC registration'   => EnterpriseProfile::YES_NO_PROGRESS[$p?->obfc_status ?? ''] ?? null,
            'Employees'           => $p?->employees !== null
                ? $p->employees.' now'.($p->expected_jobs ? ' · '.$p->expected_jobs.' new jobs expected' : '')
                : null,
            'Women / youth owned or managed' => $p ? 'Women: '.$this->yn($p->women_owned_managed).' · Youth: '.$this->yn($p->youth_owned_managed) : null,
        ]);
        if (filled($dd->summary)) {
            $mail->line($this->flat($dd->summary, 600));
        }

        /* ---------------- Transaction ---------------- */
        $expected = EnterpriseProfile::matchingGrant($v['private'] ?: null);
        $mail->line('### The transaction');
        $this->facts($mail, [
            'Investor(s)'               => $dd->investor_names ?: ($eoi->investor_name ?: '—'),
            'Stage'                     => DueDiligence::STAGES[$dd->stage][0] ?? null,
            'Instrument'                => DueDiligence::INSTRUMENTS[$dd->instrument] ?? null,
            'Private capital committed' => $this->money($v['private'] ?: null),
            'CAFI grant'                => $this->money($v['grant'] ?: null)
                .($expected ? ' — maximum '.EnterpriseProfile::grantRatioLabel().' of private capital = '.$this->money($expected) : ''),
            'Total transaction'         => $this->money($v['total'] ?: null),
            'Total project cost'        => $p?->project_cost ? $this->money($p->project_cost) : null,
            'ESGC risk'                 => DueDiligence::RATINGS[$dd->esgc_category] ?? null,
        ]);

        /* ---------------- Due diligence checklist ---------------- */
        $mail->line('### Due diligence checklist');
        foreach (DueDiligence::AREAS as $k => [$title]) {
            $st = $form['areas'][$k]['status'] ?? null;
            $fi = trim((string) ($form['areas'][$k]['finding'] ?? ''));
            $mail->line('- **'.$title.':** '.(DueDiligence::STATUSES[$st][0] ?? 'Not assessed').($fi !== '' ? ' — '.$this->flat($fi, 250) : ''));
        }

        /* ---------------- Investment Appraisal Memorandum ---------------- */
        $memo = DueDiligence::memoRows($dd->memo);
        if (collect($memo)->contains(fn ($r) => filled(trim($r['text'])))) {
            $mail->line('### Investment Appraisal Memorandum');
            foreach (DueDiligence::MEMO_QUESTIONS as $k => [$title, $question]) {
                $answer = trim($memo[$k]['text']);
                $mail->line('**'.$this->num($k).'. '.$title.'** — *'.$question.'*')
                     ->line($answer !== '' ? $this->flat($answer, 700) : 'Not answered.');
            }
        }

        /* ---------------- Outcomes, risks, conditions ---------------- */
        if (filled($dd->expected_outcomes) || filled($dd->key_risks)) {
            $mail->line('### Outcomes and risks');
            $this->facts($mail, [
                'Expected outcomes' => filled($dd->expected_outcomes) ? $this->flat($dd->expected_outcomes, 400) : null,
                'Key risks'         => filled($dd->key_risks) ? $this->flat($dd->key_risks, 400) : null,
            ]);
        }

        $open = $dd->conditions->where('status', 'open');
        $mail->line('### Conditions ('.$open->count().' open)');
        if ($open->isEmpty()) {
            $mail->line('No open conditions.');
        } else {
            foreach ($open as $c) {
                $mail->line('- '.$this->flat($c->finding ?: '—', 200)
                    .' *('.(DueDiligenceCondition::TREATMENTS[$c->treatment][0] ?? $c->treatment)
                    .' · '.(DueDiligenceCondition::OWNERS[$c->owner] ?? $c->owner).')*');
            }
        }

        if (filled($dd->conclusion)) {
            $mail->line('### PMU conclusion')->line($this->flat($dd->conclusion, 800));
        }

        /* ---------------- Next steps ---------------- */
        $mail->line('---')
             ->action('Open the Committee review', $this->applicationUrl())
             ->line('On the Committee tab: declare any conflict of interest, comment on each item of the information pack, '
                 .'and score the transaction on your scorecard. Scores stay hidden from other members until everyone has submitted.');

        if ($pdf = $this->memorandumPdf()) {
            $mail->attachData($pdf, MemorandumPdf::filename($dd), ['mime' => 'application/pdf'])
                 ->line('The full Investment Appraisal Memorandum is attached as a PDF.');
        }

        return $mail->line('This summary is confidential and for the Investment Committee only.');
    }

    /* ---------------- Helpers ---------------- */

    /** Label: value lines, skipping empty values */
    protected function facts(MailMessage $mail, array $rows): void
    {
        foreach ($rows as $label => $value) {
            if (filled($value)) {
                $mail->line('**'.$label.':** '.$value);
            }
        }
    }

    /** One line of plain text (no line breaks, trimmed to $limit characters) */
    protected function flat(?string $text, int $limit = 1000): string
    {
        return Str::limit(trim(preg_replace('/\s+/', ' ', strip_tags((string) $text))), $limit);
    }

    protected function yn($v): string
    {
        return $v === null ? '—' : ($v ? 'Yes' : 'No');
    }

    protected function num(string $key): int
    {
        return array_search($key, array_keys(DueDiligence::MEMO_QUESTIONS), true) + 1;
    }

    /** Maloti → "M 900,000 (US$ 50,000)" */
    protected function money($lsl): string
    {
        $rate = max((float) config('cif.usd_to_lsl', 18.00), 0.0001);

        return $lsl === null || $lsl === '' ? '—' : 'M '.number_format((float) $lsl).' (US$ '.number_format((float) $lsl / $rate).')';
    }

    /** The memorandum PDF, or null if it can't be generated (e-mail still goes out) */
    protected function memorandumPdf(): ?string
    {
        if (! $this->pdfTried) {
            $this->pdfTried = true;
            try {
                $this->pdf = MemorandumPdf::make($this->dd)->output();
            } catch (\Throwable $e) {
                report($e);
                $this->pdf = null;
            }
        }

        return $this->pdf;
    }

    protected function applicationUrl(): string
    {
        return Route::has('expression-of-interest.show')
            ? route('expression-of-interest.show', ['id' => $this->eoi->id]).'?tab=g3'
            : url('/');
    }
}