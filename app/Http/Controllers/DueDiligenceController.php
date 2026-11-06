<?php

namespace App\Http\Controllers;

use App\Models\DueDiligence;
use App\Support\MemorandumPdf;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Symfony\Component\HttpFoundation\Response;

class DueDiligenceController extends Controller
{
    /**
     * Investment Appraisal Memorandum — staff and Investment Committee only.
     *   default    → PDF generated from the memorandum written on the platform (Gate 2 · section 4)
     *   ?file=1    → the memorandum uploaded before the platform memo existed (older assessments)
     * An older assessment with an upload but no platform memo returns the upload.
     */
    public function memorandum(Request $request, DueDiligence $dueDiligence): Response
    {
        $user = $request->user();

        abort_unless($user->can('eoi.view-all') || $user->can('gate2.assess') || $user->can('gate2.memo') || $user->can('gate3.score')
            || $user->can('gate3.decide') || $user->can('gate4.md') || $user->can('gate4.ps'), 403);

        $hasFile = $dueDiligence->iam_path && Storage::disk('local')->exists($dueDiligence->iam_path);
        $hasMemo = collect(DueDiligence::memoRows($dueDiligence->memo))->contains(fn ($r) => filled(trim($r['text'])));

        if ($request->boolean('file') || (! $hasMemo && $hasFile && $dueDiligence->isDecided())) {
            abort_unless($hasFile, 404);

            return Storage::disk('local')->download($dueDiligence->iam_path, $dueDiligence->iam_name);
        }

        return MemorandumPdf::make($dueDiligence)->download(MemorandumPdf::filename($dueDiligence));
    }
}