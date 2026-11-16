<?php

namespace App\Notifications;

use App\Models\Authorisation;
use App\Models\ExpressionOfInterest;
use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;
use Illuminate\Support\Facades\Route;

/**
 * G5 · Staff e-mail when an outcome is recorded.
 *   $role 'md' → Managing Director review: satisfied (forward to the PS by e-mail next) · return · not approve
 *   $role 'ps' → Principal Secretary signature uploaded = formally approved (hand over to Gate 6)
 *               (older rounds may hold a PS return / not approve)
 * Includes the outcome, who reviewed / signed and their comments (internal), and what to do next.
 */
class EoiAuthorisationUpdate extends Notification
{
    use Queueable;

    public function __construct(
        public ExpressionOfInterest $eoi,
        public Authorisation $auth,
        public string $role,   // md | ps
    ) {}

    public function via(object $notifiable): array
    {
        return ['mail'];
    }

    public function toMail(object $notifiable): MailMessage
    {
        $eoi  = $this->eoi;
        $a    = $this->auth;
        $role = $this->role;

        $authority = $role === 'md' ? 'CAFI Managing Director' : 'MTIBD Principal Secretary';
        $decision  = $a->{"{$role}_decision"};
        $label     = ($role === 'md' ? Authorisation::MD_DECISIONS : Authorisation::PS_DECISIONS)[$decision][0] ?? $decision;
        $comments  = $a->{"{$role}_comments"};
        $business  = $eoi->business_name ?: $eoi->reference;

        $mail = (new MailMessage)
            ->subject('Gate 5 · '.$authority.': '.$label.' — '.$eoi->reference.($eoi->business_name ? ' ('.$eoi->business_name.')' : ''))
            ->greeting('Hello '.($notifiable->name ?: '').',')
            ->line('The '.$authority.'’s '.($role === 'md' ? 'review' : 'signature').' on **'.$business.'** (reference **'.$eoi->reference.'**'
                .($a->round > 1 ? ', round '.$a->round : '').') has been recorded: **'.$label.'**.')
            ->line('**'.($role === 'md' ? 'Reviewed by' : 'Signed by').':** '.$a->signerName($role).($a->{"{$role}_on"} ? ' on '.$a->{"{$role}_on"}->format('d M Y') : ''));

        if (filled($comments)) {
            $mail->line('**Comments:**')
                 ->line('> '.str_replace(["\r\n", "\n"], ' ', $comments));
        }

        $mail->line('**Next step:** '.$this->nextStep($role, $decision));

        return $mail->action('Open the application', Route::has('expression-of-interest.show')
            ? route('expression-of-interest.show', ['id' => $eoi->id]).'?tab=g4'
            : url('/'));
    }

    protected function nextStep(string $role, ?string $decision): string
    {
        $openConditions = collect($this->auth->conditions ?? [])->where('status', 'open')->count();

        return match ("$role.$decision") {
            'md.approve'   => 'forward the documents to the **MTIBD Principal Secretary** by e-mail for signature, '
                             .'then upload the signed documents in G5 · step 3 to complete the authorisation.',
            'md.return'    => 'resolve the matters raised and **resubmit** — it goes back to the Managing Director.',
            'md.decline'   => 'the grant is not approved and does not proceed. The applicant has been notified.',
            'ps.authorise' => 'the Principal Secretary has signed — the grant is **formally approved**. The fully executed Grant Agreement and the Grant Readiness Memo '
                             .'go to the programme team, and the transaction is handed over to **Gate 6** for conditions precedent and onboarding'
                             .($openConditions ? ' — '.$openConditions.' '.($openConditions === 1 ? 'condition' : 'conditions').' still open' : '')
                             .'. No payment is made until Gate 6 is complete.',
            'ps.clarify'   => 'resolve the matters raised and **resubmit** — it goes back to the Principal Secretary (the Managing Director’s signature stands).',
            'ps.decline'   => 'the grant is not approved and cannot proceed. The applicant has been notified.',
            default        => 'see the G5 tab.',
        };
    }
}