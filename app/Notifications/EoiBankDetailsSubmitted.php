<?php

namespace App\Notifications;

use App\Models\EnterpriseProfile;
use App\Models\ExpressionOfInterest;
use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;
use Illuminate\Support\Facades\Route;

/**
 * G4 · Grant readiness — to Grants Management and the PMU: the applicant has entered their bank details
 * and uploaded the bank confirmation letter. Verify them in the Grant readiness tab.
 */
class EoiBankDetailsSubmitted extends Notification
{
    use Queueable;

    public function __construct(
        public ExpressionOfInterest $eoi,
        public ?EnterpriseProfile $profile = null,
    ) {}

    public function via(object $notifiable): array
    {
        return ['mail'];
    }

    public function toMail(object $notifiable): MailMessage
    {
        $eoi  = $this->eoi;
        $p    = $this->profile;
        $name = $eoi->business_name ?: $eoi->reference;
        $num  = (string) $p?->bank_account_number;
        $mask = strlen($num) > 4 ? str_repeat('•', max(0, strlen($num) - 4)).substr($num, -4) : $num;

        $mail = (new MailMessage)
            ->subject('Grant readiness: bank details received — '.$eoi->reference.($eoi->business_name ? ' ('.$eoi->business_name.')' : ''))
            ->greeting('Hello '.($notifiable->name ?? '').',')
            ->line('The applicant for **'.$name.'** has entered the bank account for the grant and uploaded a bank confirmation letter.');

        if ($p) {
            $mail->line('**Account:** '.($p->bank_account_name ?: '—').' · '.($p->bank_name ?: '—').($p->bank_branch ? ', '.$p->bank_branch : '').' · '.($mask ?: '—'));
        }

        return $mail
            ->line('**Next step:** check the details against the letter and record the result in the Bank account check (G4 tab · step 1).')
            ->action('Open grant readiness', Route::has('expression-of-interest.show')
                ? route('expression-of-interest.show', ['id' => $eoi->id]).'?tab=readiness#g4-bank'
                : url('/'));
    }
}