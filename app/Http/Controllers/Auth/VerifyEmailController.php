<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Models\User;
use Illuminate\Auth\Events\Verified;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

/**
 * E-mail verification link (sent after registration with Laravel's default VerifyEmail notification).
 * Works without being signed in — new enterprises can't sign in until they have verified.
 *   Route: GET /email/verify/{id}/{hash} → name "verification.verify"  (middleware: signed, throttle:6,1)
 */
class VerifyEmailController extends Controller
{
    public function __invoke(Request $request, string $id, string $hash): RedirectResponse
    {
        $user = User::find($id);

        // Same checks as Laravel's EmailVerificationRequest, without requiring a session
        if (! $user || ! hash_equals(sha1($user->getEmailForVerification()), $hash)) {
            return redirect('/')->with('verification_error', 'This verification link is invalid. Sign in to get a new one.');
        }

        if (! $user->hasVerifiedEmail()) {
            $user->markEmailAsVerified();
            event(new Verified($user));
        }

        return redirect('/')->with('status', 'Your e-mail address has been verified. You can now sign in.');
    }
}