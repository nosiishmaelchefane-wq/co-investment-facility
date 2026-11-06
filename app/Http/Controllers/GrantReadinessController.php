<?php

namespace App\Http\Controllers;

use App\Models\EnterpriseProfile;
use App\Models\GrantReadiness;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Symfony\Component\HttpFoundation\Response;

/**
 * Gate 4 · Grant readiness files — staff who can view the transaction (not the applicant).
 *   GET /grant-readiness/{grantReadiness}/memo          → grant-readiness.memo   Grant Readiness Memo (PDF, generated)
 *   GET /grant-readiness/{grantReadiness}/file/{file}   → grant-readiness.file   agreement | bank_evidence
 */
class GrantReadinessController extends Controller
{
    public function memo(GrantReadiness $grantReadiness): Response
    {
        $this->authorizeView($grantReadiness);

        $g   = $grantReadiness->loadMissing('expressionOfInterest.user', 'committeeReview', 'grantsOfficer:id,name', 'hubManager:id,name', 'director:id,name');
        $eoi = $g->expressionOfInterest;

        $pdf = Pdf::loadView('pdf.grant-readiness-memo', [
            'g'       => $g,
            'eoi'     => $eoi,
            'profile' => EnterpriseProfile::where('user_id', $eoi->user_id)->first(),
            'facts'   => $g->facts(),
            'rate'    => max((float) config('cif.usd_to_lsl', 18.00), 0.0001),
        ])->setPaper('a4');

        return $pdf->download('Grant Readiness Memo - '.Str::slug($eoi->reference.' '.($eoi->business_name ?? '')).' - round '.$g->round.'.pdf');
    }

    public function file(GrantReadiness $grantReadiness, string $file): Response
    {
        abort_unless(in_array($file, ['agreement', 'bank_evidence'], true), 404);
        $this->authorizeView($grantReadiness);

        $path = $grantReadiness->{"{$file}_path"};
        abort_unless($path && Storage::disk(GrantReadiness::DISK)->exists($path), 404);

        return Storage::disk(GrantReadiness::DISK)->download($path, $grantReadiness->{"{$file}_name"} ?: basename($path));
    }

    protected function authorizeView(GrantReadiness $g): void
    {
        $eoi  = $g->expressionOfInterest;
        $user = auth()->user();

        abort_unless($eoi && $eoi->isViewableBy($user) && $eoi->user_id !== $user->id, 403);
    }
}