<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\User;
use Illuminate\Http\Request;

class CollaborationProfileController extends Controller
{
    /** GET /api/collaboration/profile-status?email=...  → 200 if an account exists with this e-mail, 404 if not */
    public function show(Request $request)
    {
        $email = strtolower(trim((string) $request->query('email')));

        $user = User::whereRaw('LOWER(email) = ?', [$email])->first();

        if (! $user) {
            return response()->json([
                'exists'  => false,
                'message' => 'No account found for this e-mail on the Co-Investment platform.',
            ], 404);
        }

        return response()->json([
            'exists'  => true,
            'message' => 'Account found.',
            'name'    => $user->name,
        ], 200);
    }
}