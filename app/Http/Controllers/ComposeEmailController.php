<?php

namespace App\Http\Controllers;

use App\Models\SentEmail;
use App\Support\EmailRecipients;
use Illuminate\Support\Facades\Storage;
use Symfony\Component\HttpFoundation\StreamedResponse;

/**
 * Compose Email
 *   GET /compose-email                         → the page (Livewire components in compose-email/)   name: compose.email
 *   GET /compose-email/{email}/attachment/{i}  → download an attachment of a saved e-mail           name: compose.email.attachment
 * Staff only (EmailRecipients::canCompose). Other people's drafts are private.
 */
class ComposeEmailController extends Controller
{
    public function index()
    {
        abort_unless(EmailRecipients::canCompose(auth()->user()), 403);

        return view('compose-email.index');
    }

    public function attachment(SentEmail $email, int $i): StreamedResponse
    {
        abort_unless(EmailRecipients::canCompose(auth()->user()), 403);
        abort_if($email->isDraft() && $email->sender_id !== auth()->id(), 403);

        $file = ((array) $email->attachments)[$i] ?? null;
        abort_unless($file && ! empty($file['path']) && Storage::disk(SentEmail::DISK)->exists($file['path']), 404);

        return Storage::disk(SentEmail::DISK)->download($file['path'], $file['name'] ?? basename($file['path']));
    }
}