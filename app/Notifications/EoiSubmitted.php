<?php

namespace App\Notifications;

use App\Models\ExpressionOfInterest;
use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;
use Illuminate\Support\Facades\Route;

/**
 * Sent when an Expression of Interest is submitted (or resubmitted after edits).
 *   audience "applicant" → the enterprise: confirmation with its reference and what happens next
 *   audience "pmu"       → PMU officers (gate1.decide / gate2.assess): a new application is waiting for screening
 * Amounts in Maloti with US$.
 */
class EoiSubmitted extends Notification
{
    use Queueable;

    public const APPLICANT = 'applicant';
    public const PMU       = 'pmu';

    public function __construct(
        public ExpressionOfInterest $eoi,
        public string $audience = self::APPLICANT,
        public bool $resubmitted = false,
    ) {}

    public function via(object $notifiable): array
    {
        return ['mail'];
    }

    public function toMail(object $notifiable): MailMessage
    {
        return $this->audience === self::PMU ? $this->pmuMail($notifiable) : $this->applicantMail($notifiable);
    }

    /* ---------------- To the enterprise ---------------- */

    protected function applicantMail(object $notifiable): MailMessage
    {
        $eoi  = $this->eoi;
        $name = $eoi->business_name ?: 'your enterprise';

        return (new MailMessage)
            ->subject(($this->resubmitted ? 'Application resubmitted: ' : 'Application received: ').$eoi->reference)
            ->greeting('Hello '.($eoi->representative_name ?: ($notifiable->name ?? '')).',')
            ->line($this->resubmitted
                ? 'Your updated Expression of Interest for **'.$name.'** has been resubmitted to the CAFI Co-Investment Facility.'
                : 'Thank you. Your Expression of Interest for **'.$name.'** has been submitted to the CAFI Co-Investment Facility.')
            ->line('**Reference:** '.$eoi->reference)
            ->line('**Submitted on:** '.($eoi->submitted_at ?? now())->format('d M Y, H:i'))
            ->line('**Investor amount:** '.$this->money($eoi->investor_amount_usd))
            ->line('The Project Management Unit (PMU) will now screen your application for eligibility. You will receive an e-mail when a decision is made, or if any information needs to be corrected.')
            ->action('View your application', $this->applicationUrl())
            ->line('Please quote your reference **'.$eoi->reference.'** in any correspondence about this application.')
            ->line('Submitting an application does not guarantee the award of a grant.');
    }

    /* ---------------- To the PMU ---------------- */

    protected function pmuMail(object $notifiable): MailMessage
    {
        $eoi = $this->eoi;

        $mail = (new MailMessage)
            ->subject(($this->resubmitted ? 'Application resubmitted: ' : 'New application submitted: ')
                .$eoi->reference.($eoi->business_name ? ' — '.$eoi->business_name : ''))
            ->greeting('Hello '.($notifiable->name ?? '').',')
            ->line($this->resubmitted
                ? 'The application **'.($eoi->business_name ?: $eoi->reference).'** (reference **'.$eoi->reference.'**) has been updated and resubmitted by the enterprise.'
                : 'A new Expression of Interest has been submitted and is waiting for **Gate 1 · Eligibility screening**.')
            ->line('**Reference:** '.$eoi->reference)
            ->line('**Enterprise:** '.($eoi->business_name ?: '—').($eoi->district ? ' ('.$eoi->district.')' : ''))
            ->line('**Contact:** '.(collect([$eoi->representative_name, $eoi->email, $eoi->phone])->filter()->implode(' · ') ?: '—'))
            ->line('**Investment required:** '.$this->money($eoi->investment_required_usd))
            ->line('**Investor:** '.($eoi->investor_name ?: '—').' · '.$this->money($eoi->investor_amount_usd))
            ->line('**Submitted on:** '.($eoi->submitted_at ?? now())->format('d M Y, H:i'));

        if (filled($eoi->screening_outcome)) {
            $mail->line('**Automatic pre-screening:** '.ucfirst(str_replace('_', ' ', $eoi->screening_outcome)));
        }

        return $mail->action('Open the application', $this->applicationUrl());
    }

    /* ---------------- Helpers ---------------- */

    /** Stored in US$ → "M 900,000 (US$ 50,000)" */
    protected function money($usd): string
    {
        if ($usd === null || $usd === '') {
            return '—';
        }

        $rate = max((float) config('cif.usd_to_lsl', 18.00), 0.0001);

        return 'M '.number_format((float) $usd * $rate).' (US$ '.number_format((float) $usd).')';
    }

    protected function applicationUrl(): string
    {
        return Route::has('expression-of-interest.show')
            ? route('expression-of-interest.show', ['id' => $this->eoi->id])
            : url('/');
    }
}