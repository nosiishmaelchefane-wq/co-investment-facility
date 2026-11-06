<?php

namespace App\Notifications\Gate5;

use App\Models\ConditionReview;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Support\Facades\Storage;

/**
 * G6 · Step 1 — formal communication of the grant approval to the enterprise, initiating onboarding (§3.7.1):
 *   executed Grant Agreement (attached), approved grant and budget, the conditions to fulfil before disbursement
 *   (with the evidence needed and who provides it), reporting requirements and monitoring arrangements.
 *   Makes the distinction clear: grant approved ≠ released; conditions govern release; obligations continue afterwards.
 */
class GrantApprovalNotice extends Gate5Mail
{
    public function toMail(object $notifiable): MailMessage
    {
        $r = $this->review;
        $o = (array) ($r->onboarding ?? []);

        $mail = $this->start($notifiable, 'Your CAFI grant is approved — next steps for '.$this->eoi->reference)
            ->line('We are pleased to confirm that the CAFI grant'.$this->forBusiness().' ('.$this->ref().') has been **formally approved**: '
                .'the Grant Agreement has been signed by the CAFI Managing Director and the MTIBD Principal Secretary.')
            ->line('**Approved CAFI grant:** '.$this->money($r->grant_amount))
            ->line('**Please note the difference between:**')
            ->line('1. **The grant approved** — the amount above, set out in the Grant Agreement;')
            ->line('2. **The conditions for its release** — listed below; no payment can be made until they are met and verified, '
                .'and the grant follows your investor’s verified capital, possibly in tranches;')
            ->line('3. **Your obligations after receiving the funds** — using the grant as budgeted, keeping records and reporting.');

        $budget = collect($r->budget ?? [])->filter(fn ($b) => (float) ($b['grant'] ?? 0) > 0);
        if ($budget->isNotEmpty()) {
            $mail->line('**Approved grant utilisation budget:**');
            foreach ($budget as $b) {
                $mail->line('- '.$b['category'].' — '.$this->money($b['grant']));
            }
            $mail->line('Any change to these categories or amounts must be approved before it is implemented.');
        }

        $before = $r->conditions()->where('timing', '!=', 'ongoing')->where('status', 'outstanding')->orderBy('position')->get();
        if ($before->isNotEmpty()) {
            $mail->line('**Conditions to fulfil before disbursement:**');
            foreach ($before as $i => $c) {
                $mail->line(($i + 1).'. **'.$c->finding.'**'
                    .($c->evidence_required ? ' — evidence: '.$c->evidence_required : '')
                    .' *('.(ConditionReview::PARTIES[$c->responsible] ?? 'Enterprise').')*');
            }
            $mail->line('Upload evidence for the items that are yours on the application’s conditions tab.');
        }

        $ongoing = $r->conditions()->where('timing', 'ongoing')->orderBy('position')->get();
        if ($ongoing->isNotEmpty()) {
            $mail->line('**Continuing obligations (after the grant is released):**');
            foreach ($ongoing as $c) {
                $mail->line('- '.$c->finding);
            }
        }

        $mail->line('**Reporting:** '.(ConditionReview::FREQUENCIES[$o['frequency'] ?? ''] ?? 'as set out in the Grant Agreement')
            .(! empty($o['first_report_on']) ? ' — first report due '.\Illuminate\Support\Carbon::parse($o['first_report_on'])->format('d M Y') : '')
            .(! empty($o['contact']) ? ' · Your contact: '.$o['contact'] : ''));

        // Executed Grant Agreement
        $agr  = $r->agreements()->where('kind', 'grant')->first();
        $disk = Storage::disk(ConditionReview::DISK);
        if ($agr?->file_path && $disk->exists($agr->file_path) && $disk->size($agr->file_path) <= 8 * 1024 * 1024) {
            $mail->attachData($disk->get($agr->file_path), $agr->file_name ?: 'Grant Agreement.pdf', ['mime' => $disk->mimeType($agr->file_path) ?: 'application/pdf'])
                ->line('Your **executed Grant Agreement** is attached. Please keep it for your records.');
        } elseif ($agr?->file_path) {
            $mail->line('Your **executed Grant Agreement** can be downloaded on the application page.');
        }

        return $mail->action('View my application', $this->url())
            ->line('The LEHSFF team will contact you to complete onboarding. Approval of the Grant Agreement does not, in itself, authorise payment.');
    }
}