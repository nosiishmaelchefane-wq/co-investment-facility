<?php

namespace App\Notifications;

use App\Models\ExpressionOfInterest;
use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;
use Illuminate\Support\Facades\Route;

/**
 * G4 · Grant readiness — to the applicant: please enter the bank account the grant will be paid into
 * and upload a bank confirmation letter. The link opens the Grant readiness tab of their application.
 */
class EoiBankDetailsRequested extends Notification
{
    use Queueable;

    public function __construct(
        public ExpressionOfInterest $eoi,
        public bool $reminder = false,
    ) {}

    public function via(object $notifiable): array
    {
        return ['mail'];
    }

    public function toMail(object $notifiable): MailMessage
    {
        $eoi  = $this->eoi;
        $name = $eoi->business_name ?: $eoi->reference;
        $url  = Route::has('expression-of-interest.show')
            ? route('expression-of-interest.show', ['id' => $eoi->id]).'?tab=readiness#g4-bank'
            : url('/');

        return (new MailMessage)
            ->subject(($this->reminder ? 'Reminder: ' : '').'Bank details needed for your CAFI grant — '.$eoi->reference)
            ->greeting('Hello '.($notifiable->name ?? '').',')
            ->line('Your application for **'.$name.'** (reference **'.$eoi->reference.'**) is being prepared for final authorisation.')
            ->line('To pay the grant, the LEHSFF team needs the details of the bank account the grant will be paid into:')
            ->line('• the bank and branch')
            ->line('• the account name — it must be the company’s registered name, not a personal account')
            ->line('• the account number')
            ->line('• a **bank confirmation letter** (or a recent bank statement with the bank’s stamp) showing the account name and number')
            ->action('Enter your bank details', $url)
            ->line('The link opens the Grant readiness section of your application. Sign in if you are asked to.')
            ->line('If you have any questions, reply to this e-mail or contact the LEHSFF team.');
    }
}