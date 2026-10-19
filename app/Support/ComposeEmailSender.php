<?php

namespace App\Support;

use App\Models\ExpressionOfInterest;
use App\Models\SentEmail;
use App\Models\User;
use App\Notifications\ComposedEmail;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Str;

/**
 * Compose Email · sends a SentEmail record and writes back what happened.
 *
 *   Individually ON  → every recipient gets their own e-mail with their own merge fields
 *                      (Cc / Bcc addresses each get their own copy too — nobody sees anyone else's address).
 *   Individually OFF → one e-mail: every recipient in "To", plus the Cc and Bcc; merge fields are filled generically.
 *
 * A failed address never stops the others: failures are reported (laravel.log) and counted.
 * Many recipients? Make ComposedEmail implement ShouldQueue and run a queue worker so the page doesn't wait.
 */
class ComposeEmailSender
{
    /** @return array{sent:int, failed:int, total:int} */
    public static function send(SentEmail $record, User $sender, bool $copyMe = false): array
    {
        $spec   = array_merge(EmailRecipients::defaults(), (array) $record->recipients_spec);
        $rows   = EmailRecipients::resolve($spec);
        $linked = $record->expression_of_interest_id ? ExpressionOfInterest::find($record->expression_of_interest_id) : null;
        $cc     = EmailRecipients::parse($spec['cc']);
        $bcc    = EmailRecipients::parse($spec['bcc']);
        $files  = (array) $record->attachments;

        $sent = $failed = 0;
        $list = [];

        $deliver = function (array $to, string $subject, string $html, array $cc = [], array $bcc = []) use ($files): bool {
            try {
                Notification::route('mail', $to)->notify(new ComposedEmail($subject, $html, $files, $cc, $bcc));
                return true;
            } catch (\Throwable $e) {
                report($e);
                return false;
            }
        };

        if ($spec['individually']) {
            // Cc / Bcc addresses become extra individual recipients
            foreach ([...$cc, ...$bcc] as $email) {
                $rows->put(Str::lower($email), $rows->get(Str::lower($email)) ?? ['email' => $email, 'name' => null, 'eoi' => null]);
            }

            foreach ($rows as $r) {
                $ok = $deliver(
                    [$r['email'] => $r['name'] ?: $r['email']],
                    EmailRecipients::merge($record->subject, $r, $linked, html: false),
                    EmailRecipients::merge($record->body, $r, $linked),
                );
                $ok ? $sent++ : $failed++;
                $list[] = $r['email'];
            }
        } else {
            $to = $rows->mapWithKeys(fn ($r) => [$r['email'] => $r['name'] ?: $r['email']])->all();
            $n  = count($to) + count($cc) + count($bcc);

            if ($n) {
                $ok = $deliver(
                    $to ?: [($cc[0] ?? $bcc[0]) => ($cc[0] ?? $bcc[0])],   // only Cc/Bcc given → first Cc (or Bcc) becomes the "To"
                    EmailRecipients::merge($record->subject, null, $linked, html: false),
                    EmailRecipients::merge($record->body, null, $linked),
                    $to ? $cc : array_slice($cc, 1),
                    $to || $cc ? $bcc : array_slice($bcc, 1),
                );
                $ok ? $sent = $n : $failed = $n;
            }
            $list = [...array_keys($to), ...$cc, ...$bcc];
        }

        // A copy for the sender (not counted)
        if ($copyMe && filled($sender->email)) {
            $first = $rows->first();
            $deliver(
                [$sender->email => $sender->name],
                '[Copy] '.EmailRecipients::merge($record->subject, $first, $linked, html: false),
                '<p><em>This is your copy of an e-mail sent to '.number_format(count($list)).' '.Str::plural('recipient', count($list)).'.</em></p>'
                    .EmailRecipients::merge($record->body, $first, $linked),
            );
        }

        $total = $sent + $failed;
        $record->update([
            'recipient_list'  => array_values(array_unique($list)),
            'recipient_count' => $total,
            'sent_count'      => $sent,
            'failed_count'    => $failed,
            'status'          => $failed === 0 ? 'sent' : ($sent === 0 ? 'failed' : 'partial'),
            'sent_at'         => now(),
        ]);

        return compact('sent', 'failed', 'total');
    }
}