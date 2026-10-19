<?php

namespace App\Http\Controllers;

use App\Models\DueDiligence;
use Illuminate\Support\Facades\Storage;
use Symfony\Component\HttpFoundation\StreamedResponse;

class DueDiligenceController extends Controller
{
    /** Private download of the Investment Appraisal Memorandum — staff and Investment Committee only. */
    public function memorandum(DueDiligence $dueDiligence): StreamedResponse
    {
        $user = auth()->user();

        abort_unless($user->can('eoi.view-all') || $user->can('gate2.assess') || $user->can('gate2.memo') || $user->can('gate3.score')
            || $user->can('gate3.decide') || $user->can('gate4.md') || $user->can('gate4.ps'), 403);
        abort_unless($dueDiligence->iam_path && Storage::disk('local')->exists($dueDiligence->iam_path), 404);

        return Storage::disk('local')->download($dueDiligence->iam_path, $dueDiligence->iam_name);
    }
}