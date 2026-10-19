<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Models\User;
use App\Notifications\AccountAccessLink;
use Illuminate\Auth\Events\PasswordReset;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Password;
use Illuminate\Support\Str;
use Illuminate\Validation\Rules;
use Illuminate\View\View;

/**
 * Breeze "reset password" page — also used for invitations.
 *   Routes (Breeze, routes/auth.php):  GET reset-password/{token} → password.reset   ·   POST reset-password → password.store
 *   Invited users (status "invited") are checked against the "invites" broker (72 h links); everyone else the default broker.
 *   Setting a password activates an invited account and confirms the e-mail address (the link proved it).
 */
class NewPasswordController extends Controller
{
    public function create(Request $request): View
    {
        return view('auth.reset-password', ['request' => $request]);
    }

    public function store(Request $request): RedirectResponse
    {
        $request->validate([
            'token'    => ['required'],
            'email'    => ['required', 'email'],
            'password' => ['required', 'confirmed', Rules\Password::defaults()],
        ]);

        $user   = User::where('email', $request->email)->first();
        $invite = $user?->status === User::STATUS_INVITED;
        $broker = AccountAccessLink::brokerFor($invite ? 'invite' : 'reset');

        $status = Password::broker($broker)->reset(
            $request->only('email', 'password', 'password_confirmation', 'token'),
            function (User $user) use ($request) {
                $user->forceFill([
                    'password'          => Hash::make($request->password),
                    'remember_token'    => Str::random(60),
                    'email_verified_at' => $user->email_verified_at ?? now(),
                    'status'            => $user->status === User::STATUS_INVITED ? User::STATUS_ACTIVE : $user->status,
                ])->save();

                event(new PasswordReset($user));
            }
        );

        if ($status !== Password::PASSWORD_RESET) {
            return back()->withInput($request->only('email'))->withErrors(['email' => __($status)]);
        }

        return redirect('/')->with('status', $invite
            ? 'Your account is ready. Sign in with your new password.'
            : 'Your password has been reset. Sign in with your new password.');
    }
}