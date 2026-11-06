<?php

namespace App\Http\Controllers;

use App\Models\CapitalDeployment;
use App\Models\ConditionReview;
use App\Models\CpAgreement;
use App\Models\CpCondition;
use App\Models\DisbursementTranche;
use App\Models\EnterpriseProfile;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpFoundation\StreamedResponse;

/**
 * Private downloads for Gate 6 files: condition evidence, signed agreements, investor capital evidence,
 *   tranche proof of payment.
 *   Route: GET /gate5/{kind}/{id}/download  → name "gate5.file"   (kind: condition | agreement | deployment | tranche)
 *   Record: GET /gate5/{review}/record → name "gate5.record" — Gate 6/7 disbursement file (PDF): CP checklist, verification
 *   record, readiness confirmation, disbursement record, onboarding record. Staff only.
 *   Allowed: anyone who can view the transaction (applicant, staff with eoi.view-all, Finance at Gate 6).
 */
class Gate5FileController extends Controller
{
    public function download(string $kind, int $id): StreamedResponse
    {
        [$model, $pathCol, $nameCol] = match ($kind) {
            'condition'  => [CpCondition::findOrFail($id),       'evidence_path', 'evidence_name'],
            'agreement'  => [CpAgreement::findOrFail($id),       'file_path',     'file_name'],
            'deployment' => [CapitalDeployment::findOrFail($id), 'evidence_path', 'evidence_name'],
            'tranche'    => [DisbursementTranche::findOrFail($id), 'proof_path',  'proof_name'],
            default      => abort(404),
        };

        $eoi = $model->review?->expressionOfInterest;
        abort_unless($eoi && $eoi->isViewableBy(auth()->user()), 403);
        abort_unless($model->{$pathCol} && Storage::disk(ConditionReview::DISK)->exists($model->{$pathCol}), 404);

        return Storage::disk(ConditionReview::DISK)->download($model->{$pathCol}, $model->{$nameCol});
    }

    public function record(ConditionReview $review): Response
    {
        $review->loadMissing(['expressionOfInterest.user', 'conditions.verifiedBy:id,name', 'agreements', 'deployments.verifier:id,name', 'deployments.tranche',
            'tranches.requester:id,name', 'tranches.approver:id,name', 'tranches.releaser:id,name', 'decider:id,name',
            'readinessPmu:id,name', 'readinessFinance:id,name', 'authorisation']);
        $eoi = $review->expressionOfInterest;
        abort_unless($eoi && $eoi->isViewableBy(auth()->user()) && $eoi->user_id !== auth()->id(), 403);

        $pdf = Pdf::loadView('pdf.disbursement-file', [
            'r'       => $review,
            'eoi'     => $eoi,
            'profile' => EnterpriseProfile::where('user_id', $eoi->user_id)->first(),
            'rate'    => max((float) config('cif.usd_to_lsl', 18.00), 0.0001),
        ])->setPaper('a4');

        return $pdf->download('Disbursement File - '.Str::slug($eoi->reference.' '.($eoi->business_name ?? '')).'.pdf');
    }
}