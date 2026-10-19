<?php

namespace App\Http\Controllers;

use App\Models\Authorisation;
use Illuminate\Support\Facades\Storage;
use Symfony\Component\HttpFoundation\StreamedResponse;

/**
 * Gate 4 files: the document e-mailed for signing and the signed copy, for the MD and the PS.
 *   Route: GET /gate4/{authorisation}/{file}/download → name "gate4.file"   (file: md_request | md_signed | ps_request | ps_signed)
 *   Allowed: staff who can view the transaction (not the applicant).
 */
class Gate4FileController extends Controller
{
    public function download(Authorisation $authorisation, string $file): StreamedResponse
    {
        abort_unless(in_array($file, ['md_request', 'md_signed', 'ps_request', 'ps_signed'], true), 404);

        $eoi  = $authorisation->expressionOfInterest;
        $user = auth()->user();
        abort_unless($eoi && $eoi->isViewableBy($user) && $eoi->user_id !== $user->id, 403);

        $path = $authorisation->{"{$file}_path"};
        abort_unless($path && Storage::disk(Authorisation::DISK)->exists($path), 404);

        return Storage::disk(Authorisation::DISK)->download($path, $authorisation->{"{$file}_name"} ?: basename($path));
    }
}