<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Lets the CAFI Co-Investment platform call the matchmaking API.
 *   Header:  Authorization: Bearer <CIF_API_TOKEN>
 * Settings: config/api.php → 'cif'.
 */
class VerifyCifApiToken
{
    public function handle(Request $request, Closure $next): Response
    {
        $expected = (string) config('api.cif.token');

        if ($expected === '') {
            return response()->json(['message' => 'The CIF API is not configured (CIF_API_TOKEN is empty).'], 503);
        }

        if (! hash_equals($expected, (string) $request->bearerToken())) {
            return response()->json(['message' => 'Unauthorised.'], 401);
        }

        return $next($request);
    }
}