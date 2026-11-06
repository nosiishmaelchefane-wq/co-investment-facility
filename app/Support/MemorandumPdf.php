<?php

namespace App\Support;

use App\Models\DueDiligence;
use App\Models\EnterpriseProfile;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Support\Str;

/**
 * Builds the Investment Appraisal Memorandum PDF (resources/views/pdf/investment-appraisal-memorandum.blade.php).
 * Used for the download (DueDiligenceController::memorandum) and the attachment on EoiReadyForCommittee.
 */
class MemorandumPdf
{
    /** @return \Barryvdh\DomPDF\PDF */
    public static function make(DueDiligence $dd)
    {
        $dd->loadMissing('conditions', 'assessor:id,name', 'decider:id,name', 'expressionOfInterest.user');

        $eoi     = $dd->expressionOfInterest;
        $profile = EnterpriseProfile::with('documents', 'investments')->where('user_id', $eoi->user_id)->first();

        // Nothing written yet: draft it now so the PDF is never empty
        $memo = DueDiligence::memoRows($dd->memo);
        if (! collect($memo)->contains(fn ($r) => filled(trim($r['text'])))) {
            $conds = $dd->conditions->map->only(['finding', 'treatment', 'owner', 'status'])->all();
            $memo  = DueDiligence::refreshMemo(['memo' => []] + $dd->toForm(), $conds, $eoi, $profile);
        }

        return Pdf::loadView('pdf.investment-appraisal-memorandum', [
            'dd'      => $dd,
            'eoi'     => $eoi,
            'profile' => $profile,
            'memo'    => $memo,
            'v'       => DueDiligence::validated($dd->toForm()),
            'rate'    => max((float) config('cif.usd_to_lsl', 18.00), 0.0001),
        ])->setPaper('a4');
    }

    public static function filename(DueDiligence $dd): string
    {
        $eoi = $dd->expressionOfInterest;

        return 'Investment Appraisal Memorandum - '.Str::slug($eoi->reference.' '.($eoi->business_name ?? '')).' - round '.$dd->round.'.pdf';
    }
}