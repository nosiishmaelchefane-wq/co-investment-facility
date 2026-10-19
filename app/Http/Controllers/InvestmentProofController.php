<?php

namespace App\Http\Controllers;

use App\Models\EnterpriseInvestment;
use Illuminate\Support\Facades\Storage;
use Symfony\Component\HttpFoundation\StreamedResponse;

/**
 * Private download of an investment's proof (bank statement, share certificate, agreement…).
 *   Route: GET /investments/{investment}/proof → name "investments.proof"
 *   Allowed: the enterprise that owns the record, and reviewers.
 */
class InvestmentProofController extends Controller
{
    public function download(EnterpriseInvestment $investment): StreamedResponse
    {
        $user = auth()->user();

        $allowed = $investment->profile?->user_id === $user->id
            || ($user->can('eoi.view-all') && ! $user->hasRole('enterprise'))
            || $user->canAny(['gate1.decide', 'gate2.assess', 'gate3.score', 'gate4.md', 'gate4.ps']);

        abort_unless($allowed, 403);
        abort_unless($investment->proof_path && Storage::disk(EnterpriseInvestment::DISK)->exists($investment->proof_path), 404);

        return Storage::disk(EnterpriseInvestment::DISK)->download($investment->proof_path, $investment->proof_name);
    }
}