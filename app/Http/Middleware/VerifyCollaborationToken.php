<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Lets the LEHSFF Collaboration Platform call the Co-Investment API.
 *   Header:  Authorization: Bearer <COLLAB_API_TOKEN>
 * The same token is used in both directions (CIF_API_TOKEN on the Collaboration Platform).
 */
class VerifyCollaborationToken
{
    public function handle(Request $request, Closure $next): Response
    {
        $expected = (string) config('cif.collaboration.token');

        if ($expected === '') {
            return response()->json(['success' => false, 'code' => 'not_configured', 'message' => 'The Collaboration API is not configured (COLLAB_API_TOKEN is empty).', 'data' => []], 503);
        }

        if (! hash_equals($expected, (string) $request->bearerToken())) {
            return response()->json(['success' => false, 'code' => 'unauthorised', 'message' => 'Unauthorised: missing or wrong API token.', 'data' => []], 401);
        }

        return $next($request);
    }
}