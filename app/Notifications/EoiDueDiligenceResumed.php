<?php

namespace App\Notifications;

use App\Models\ExpressionOfInterest;
use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;
use Illuminate\Support\Facades\Route;

/**
 * G2 · The assessment was on hold (clarification / remediation) and has been resumed.
 * Lets the applicant know their response was received and the review continues.
 */
class EoiDueDiligenceResumed extends Notification
{
    use Queueable;

    public function __construct(public ExpressionOfInterest $eoi) {}

    public function via(object $notifiable): array
    
    {
        return ['mail'];
    }

    public function toMail(object $notifiable): MailMessage
    {
        $eoi = $this->eoi;

        return (new MailMessage)
            ->subject('Due diligence resumed for '.$eoi->reference)
            ->greeting('Hello '.($notifiable->name ?: '').',')
            ->line('Thank you for providing the information requested for your application'
                .($eoi->business_name ? ' for **'.$eoi->business_name.'**' : '')
                .' (reference **'.$eoi->reference.'**).')
            ->line('The LEHSFF team has **resumed due diligence**. You will be notified of the outcome, '
                .'and the team may contact you if anything else is needed.')
            ->action('View my application', Route::has('expression-of-interest.show')
                ? route('expression-of-interest.show', ['id' => $eoi->id])
                : url('/'));
    }
}