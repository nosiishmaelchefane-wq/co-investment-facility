<?php

namespace App\Http\Controllers;

use App\Models\User;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Validation\Rules\Password;
use Illuminate\View\View;

/**
 * Invitation / password-reset link landing page.
 * Routes (both use the `signed` middleware):
 *   GET  /account/setup/{user}  → show
 *   POST /account/setup/{user}  → store
 */
class AccountSetupController extends Controller
{
    public function show(Request $request, User $user): View
    {
        $this->guard($request, $user);

        return view('auth.account-setup', [
            'user'    => $user,
            'action'  => $request->fullUrl(),   // keep the signature for the POST
            'isReset' => $user->status !== User::STATUS_INVITED,
        ]);
    }

    public function store(Request $request, User $user): RedirectResponse
    {
        $this->guard($request, $user);

        $request->validate([
            'password' => ['required', 'confirmed', Password::defaults()],
        ]);

        $user->forceFill([
            'password'          => (string) $request->input('password'),   // hashed by the model cast
            'status'            => User::STATUS_ACTIVE,
            'email_verified_at' => $user->email_verified_at ?? now(),
            'last_login_at'     => now(),
        ])->save();

        Auth::login($user);
        $request->session()->regenerate();

        return redirect('/expression-of-interest')->with('status', 'Your password has been set. Welcome!');
    }

    /** Link must be signed, unused (hash still matches) and the account not deactivated. */
    protected function guard(Request $request, User $user): void
    {
        abort_unless(hash_equals($user->setupHash(), (string) $request->query('h')), 403, 'This link has already been used. Ask an administrator for a new one.');
        abort_if($user->status === User::STATUS_INACTIVE, 403, 'This account has been deactivated.');
    }
}