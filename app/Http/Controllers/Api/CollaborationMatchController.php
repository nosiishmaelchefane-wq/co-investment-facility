<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\EnterpriseProfile;
use App\Models\User;
use App\Support\CollaborationPlatform;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;

/**
 * POST /api/collaboration/matches
 *
 * The Collaboration Platform sends a matchmaking result as soon as an administrator matches an enterprise
 * with an investor. The enterprise is found by e-mail; its profile's 8.2 Investor information is filled in
 * (empty answers only) and marked "From matchmaking" for the evaluators.
 *
 * Body (JSON) — the same shape as GET /api/cif/enterprise-match on the Collaboration Platform:
 *   { "email": "owner@enterprise.co.ls",
 *     "enterprise": { "business_name": "..." },
 *     "match":      { "id", "status", "status_label", "score", "introduced_at", "matched_by", ... },
 *     "investor":   { "name", "contact_name", "email", "phone", "website", "country", "types", ... } }
 *
 * Answers (JSON):
 *   200 { "saved": true,  "result": "filled" | "unchanged", "message": "..." }
 *   404 { "saved": false, "message": "No account found for this e-mail on the Co-Investment platform." }
 *   422 validation error
 */
class CollaborationMatchController extends Controller
{
    public function store(Request $request)
    {
        $data = $request->validate([
            'email'             => ['required', 'email', 'max:255'],
            'enterprise'        => ['nullable', 'array'],
            'match'             => ['required', 'array'],
            'match.status'      => ['required', 'string'],
            'investor'          => ['required', 'array'],
            'investor.name'     => ['required', 'string', 'max:255'],
        ]);

        $email = strtolower(trim($data['email']));
        $user  = User::whereRaw('LOWER(email) = ?', [$email])->first();

        if (! $user) {
            Log::info('[Collaboration API] match received for an unknown e-mail', ['email' => $email]);

            return response()->json([
                'saved'   => false,
                'message' => 'No account found for this e-mail on the Co-Investment platform.',
            ], 404);
        }

        // The enterprise profile — a draft one is started from the account if it does not exist yet
        $profile = EnterpriseProfile::forUser($user);

        $result = CollaborationPlatform::apply($profile, [
            'found'      => true,
            'enterprise' => $request->input('enterprise'),
            'match'      => $request->input('match'),
            'investor'   => $request->input('investor'),
        ]);

        Log::info('[Collaboration API] match received', [
            'email'    => $email,
            'match_id' => $request->input('match.id'),
            'investor' => $request->input('investor.name'),
            'result'   => $result,
        ]);

        return response()->json([
            'saved'   => true,
            'result'  => $result,
            'message' => $result === 'filled'
                ? 'Investor information added to the enterprise profile.'
                : 'Match received — the enterprise profile already had these answers.',
        ], 200);
    }
}