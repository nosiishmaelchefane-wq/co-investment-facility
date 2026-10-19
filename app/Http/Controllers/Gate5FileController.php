<?php

namespace App\Http\Controllers;

use App\Models\CapitalDeployment;
use App\Models\ConditionReview;
use App\Models\CpAgreement;
use App\Models\CpCondition;
use App\Models\DisbursementTranche;
use Illuminate\Support\Facades\Storage;
use Symfony\Component\HttpFoundation\StreamedResponse;

/**
 * Private downloads for Gate 5 files: condition evidence, signed agreements, investor capital evidence,
 *   tranche proof of payment.
 *   Route: GET /gate5/{kind}/{id}/download  → name "gate5.file"   (kind: condition | agreement | deployment | tranche)
 *   Allowed: anyone who can view the transaction (applicant, staff with eoi.view-all, Finance at Gate 5).
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
}