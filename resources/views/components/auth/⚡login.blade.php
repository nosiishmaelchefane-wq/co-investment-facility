<?php

use App\Models\User;
use App\Notifications\LoginOtpCode;
use Illuminate\Auth\Events\Lockout;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\Facades\Session;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use Livewire\Component;

/*
| Sign-in with two steps:
|   1. E-mail + password are checked (nobody is signed in yet).
|   2. A 6-digit one-time code is e-mailed; the user is signed in only after entering it.
|   The pending user is kept in the session (not in the page), the code is stored hashed in the cache,
|   expires after OTP_MINUTES and allows OTP_ATTEMPTS tries. A new code can be requested once a minute.
|
| SWITCHES (config/cif.php → 'login', set in .env):
|   CIF_OTP_ENABLED=false      → step 2 is skipped for everyone: a correct password signs the user in straight away.
|   CIF_OTP_SKIP_BUTTON=true   → TEMPORARY: the code screen shows a "Skip code for now" button (password already checked).
|   To re-activate 2-step sign-in: CIF_OTP_ENABLED=true and CIF_OTP_SKIP_BUTTON=false.
|   Per user: when it is on, a user can switch it off for themselves in Account settings (users.two_factor_enabled)
|   — see User::requiresSignInCode().
*/
new class extends Component
{

    protected const OTP_MINUTES  = 10;
    protected const OTP_ATTEMPTS = 5;

    public string $email = '';
    public string $password = '';
    public bool $remember = false;

    /** 'credentials' | 'otp' */
    public string $step = 'credentials';
    public string $otp = '';
    public ?string $maskedEmail = null;
    public ?string $otpMessage = null;

    /** Set when the account exists but its e-mail address is not verified yet */
    public ?string $unverifiedEmail = null;
    public ?string $resendMessage = null;

    public function mount(): void
    {
        // Came back to the page with a code still pending → show the code step
        if ($this->otpEnabled() && ($id = Session::get('login.otp_user'))) {
            $user = User::find($id);
            if ($user && Cache::has($this->otpKey($id))) {
                $this->step        = 'otp';
                $this->maskedEmail = $this->mask($user->email);
            }
        }
    }

    /** Used by the view */
    public function otpEnabled(): bool
    {
        return (bool) config('cif.login.otp_enabled', false);
    }

    public function canSkipOtp(): bool
    {
        return (bool) config('cif.login.otp_skip_button', false);
    }

    /* =====================================================================
     | Step 1 · e-mail + password
     ===================================================================== */

    public function login(): void
    {
        $this->validate(['email' => 'required|string|email', 'password' => 'required|string']);

        $this->ensureIsNotRateLimited();

        // Check the password without signing in
        if (! Auth::validate(['email' => $this->email, 'password' => $this->password])) {
            RateLimiter::hit($this->throttleKey());

            throw ValidationException::withMessages(['email' => trans('auth.failed')]);
        }

        RateLimiter::clear($this->throttleKey());

        $user = User::where('email', $this->email)->firstOrFail();

        // Deactivated accounts can't sign in
        if ($user->status === User::STATUS_INACTIVE) {
            throw ValidationException::withMessages(['email' => 'Your account has been deactivated. Contact the administrator.']);
        }

        // New enterprises must click the link in the verification e-mail first
        if (! $user->hasVerifiedEmail()) {
            $this->unverifiedEmail = $user->email;
            $this->resendMessage   = null;

            throw ValidationException::withMessages([
                'email' => 'Please verify your e-mail address first — click the link we sent to '.$user->email.'.',
            ]);
        }

        // No code needed (switched off for the platform, or by this user in Account settings) → sign in now
        if (! $user->requiresSignInCode()) {
            $this->completeLogin($user, $this->remember);
            return;
        }

        // Step 2: e-mail a one-time code
        Session::put('login.otp_user', $user->id);
        Session::put('login.remember', $this->remember);

        if (! $this->sendCode($user)) {
            Session::forget(['login.otp_user', 'login.remember']);
            throw ValidationException::withMessages(['email' => 'We could not send your sign-in code right now. Please try again in a few minutes.']);
        }

        $this->reset('password', 'otp', 'otpMessage');
        $this->step        = 'otp';
        $this->maskedEmail = $this->mask($user->email);
    }

    /* =====================================================================
     | Step 2 · one-time code
     ===================================================================== */

    public function verifyOtp(): void
    {
        $this->validate(['otp' => ['required', 'digits:6']], ['otp.digits' => 'Enter the 6-digit code from the e-mail.', 'otp.required' => 'Enter the 6-digit code from the e-mail.']);

        $id      = Session::get('login.otp_user');
        $pending = $id ? Cache::get($this->otpKey($id)) : null;

        if (! $pending) {
            $this->backToLogin('Your code has expired. Please sign in again.');
            return;
        }

        if (! Hash::check($this->otp, $pending['hash'])) {
            $pending['attempts']++;

            if ($pending['attempts'] >= self::OTP_ATTEMPTS) {
                Cache::forget($this->otpKey($id));
                $this->backToLogin('Too many incorrect codes. Please sign in again.');
                return;
            }

            Cache::put($this->otpKey($id), $pending, $pending['expires']);
            $left = self::OTP_ATTEMPTS - $pending['attempts'];
            throw ValidationException::withMessages(['otp' => "That code is not correct. {$left} ".Str::plural('attempt', $left).' left.']);
        }

        // Correct code → sign in
        $this->finishPendingLogin();
    }

    /** TEMPORARY: sign in without the code (the password was already checked in step 1) */
    public function skipOtp(): void
    {
        abort_unless($this->canSkipOtp(), 403);

        if (! Session::get('login.otp_user')) {
            $this->backToLogin('Please sign in again.');
            return;
        }

        $this->finishPendingLogin();
    }

    /** Send a new code (at most once a minute) */
    public function resendOtp(): void
    {
        $user = ($id = Session::get('login.otp_user')) ? User::find($id) : null;
        if (! $user) {
            $this->backToLogin('Please sign in again.');
            return;
        }

        $key = 'login-otp-resend:'.$user->id;
        if (RateLimiter::tooManyAttempts($key, 1)) {
            $this->otpMessage = 'Please wait '.RateLimiter::availableIn($key).' seconds before requesting another code.';
            return;
        }

        $this->otpMessage = $this->sendCode($user)
            ? 'A new code has been sent to '.$this->maskedEmail.'.'
            : 'The code could not be sent right now. Please try again in a few minutes.';
        $this->reset('otp');
        $this->resetValidation('otp');
    }

    /** "Use a different account" / start again */
    public function cancelOtp(): void
    {
        if ($id = Session::get('login.otp_user')) {
            Cache::forget($this->otpKey($id));
        }
        $this->backToLogin();
    }

    /* =====================================================================
     | Verification e-mail (unchanged)
     ===================================================================== */

    /** Send the verification e-mail again (at most once a minute) */
    public function resendVerification(): void
    {
        if (! $this->unverifiedEmail) {
            return;
        }

        $key = 'verify-resend:'.Str::lower($this->unverifiedEmail);
        if (RateLimiter::tooManyAttempts($key, 1)) {
            $this->resendMessage = 'Please wait '.RateLimiter::availableIn($key).' seconds before requesting another e-mail.';
            return;
        }
        RateLimiter::hit($key, 60);

        $user = User::where('email', $this->unverifiedEmail)->first();
        if ($user && ! $user->hasVerifiedEmail()) {
            try {
                $user->sendEmailVerificationNotification();
                $this->resendMessage = 'A new verification link has been sent to '.$this->unverifiedEmail.'.';
            } catch (\Throwable $e) {
                report($e);
                $this->resendMessage = 'The e-mail could not be sent right now. Please try again in a few minutes.';
            }
        } else {
            $this->resendMessage = 'Your e-mail address is already verified — sign in again.';
        }
    }

    /* =====================================================================
     | Helpers
     ===================================================================== */

    /** Sign in the user from step 1 — after a correct code, or via the temporary skip button */
    protected function finishPendingLogin(): void
    {
        $id = Session::get('login.otp_user');

        Cache::forget($this->otpKey($id));
        $remember = (bool) Session::pull('login.remember', false);
        Session::forget('login.otp_user');

        $user = $id ? User::find($id) : null;
        if (! $user || $user->status === User::STATUS_INACTIVE) {
            $this->backToLogin('This account can no longer sign in.');
            return;
        }

        $this->completeLogin($user, $remember);
    }

    /** Sign in, record the login and go to the user's home page */
    protected function completeLogin(User $user, bool $remember): void
    {
        Auth::login($user, $remember);

        // Track last login; an invited user who signs in is now active
        $user->forceFill(['last_login_at' => now(), 'status' => User::STATUS_ACTIVE])->save();

        Session::regenerate();

        $home = $this->homeFor($user);

        // Enterprises never land on the dashboard — drop a remembered "/dashboard" so they go to their applications
        if ($this->isEnterprise($user) && Str::contains((string) Session::get('url.intended'), '/dashboard')) {
            Session::forget('url.intended');
        }

        // A page the user tried to open before signing in still wins; otherwise their home page
        $this->redirectIntended(default: $home, navigate: false);
    }

    /** Enterprise → Expressions of Interest · every other role → Dashboard */
    protected function homeFor(User $user): string
    {
        $route = $this->isEnterprise($user) || ! Route::has('dashboard') ? 'expression-of-interest.index' : 'dashboard';

        return route($route, absolute: false);
    }

    protected function isEnterprise(User $user): bool
    {
        return $user->hasRole('enterprise');
    }

    /** Create a new code, store it hashed and e-mail it. Returns false if the e-mail failed. */
    protected function sendCode(User $user): bool
    {
        $code    = str_pad((string) random_int(0, 999999), 6, '0', STR_PAD_LEFT);
        $expires = now()->addMinutes(self::OTP_MINUTES);

        Cache::put($this->otpKey($user->id), ['hash' => Hash::make($code), 'attempts' => 0, 'expires' => $expires], $expires);
        RateLimiter::hit('login-otp-resend:'.$user->id, 60);

        try {
            $user->notify(new LoginOtpCode($code, self::OTP_MINUTES));
            return true;
        } catch (\Throwable $e) {
            report($e);
            Cache::forget($this->otpKey($user->id));
            return false;
        }
    }

    protected function backToLogin(?string $message = null): void
    {
        Session::forget(['login.otp_user', 'login.remember']);
        $this->reset('otp', 'password', 'maskedEmail', 'otpMessage');
        $this->step = 'credentials';
        $this->resetValidation();

        if ($message) {
            $this->addError('email', $message);
        }
    }

    protected function otpKey(int|string|null $userId): string
    {
        return 'login-otp:'.$userId;
    }

    /** thabo@gmail.com → th***@gmail.com */
    protected function mask(string $email): string
    {
        [$name, $domain] = array_pad(explode('@', $email, 2), 2, '');

        return Str::substr($name, 0, 2).str_repeat('*', max(3, Str::length($name) - 2)).'@'.$domain;
    }

    /**
     * Ensure the login request is not rate limited (max 5 attempts).
     */
    protected function ensureIsNotRateLimited(): void
    {
        if (! RateLimiter::tooManyAttempts($this->throttleKey(), 5)) {
            return;
        }

        event(new Lockout(request()));

        $seconds = RateLimiter::availableIn($this->throttleKey());

        throw ValidationException::withMessages([
            'email' => trans('auth.throttle', [
                'seconds' => $seconds,
                'minutes' => ceil($seconds / 60),
            ]),
        ]);
    }

    /**
     * Rate-limiting key: email + IP.
     */
    protected function throttleKey(): string
    {
        return Str::transliterate(Str::lower($this->email) . '|' . request()->ip());
    }
};
?>

<div>
    @if ($step === 'otp')
        {{-- ============================ STEP 2 · ONE-TIME CODE ============================ --}}
        <div class="text-center mb-3"><i class="bi bi-shield-lock text-cif-primary" style="font-size: 2.75rem;"></i></div>
        <h2 class="h3 fw-bold text-cif-navy mb-1 text-center">Enter your sign-in code</h2>
        <p class="text-secondary small mb-4 text-center">We sent a 6-digit code to <strong>{{ $maskedEmail }}</strong>. It expires in 10 minutes.</p>

        <form wire:submit="verifyOtp" novalidate>
            <div class="mb-3">
                <label for="login_otp" class="form-label">Code</label>
                <input id="login_otp" type="text" wire:model="otp" inputmode="numeric" pattern="[0-9]*" maxlength="6" autocomplete="one-time-code" autofocus
                       placeholder="••••••"
                       class="form-control form-control-lg text-center fw-bold @error('otp') is-invalid @enderror" style="letter-spacing: .5em;">
                @error('otp') <div class="text-danger small mt-1">{{ $message }}</div> @enderror
            </div>

            @if ($otpMessage)
                <div class="alert alert-info small py-2">{{ $otpMessage }}</div>
            @endif

            <button type="submit" class="btn btn-cif-primary btn-lg w-100" wire:loading.attr="disabled" wire:target="verifyOtp">
                <span wire:loading.remove wire:target="verifyOtp">Verify &amp; sign in <i class="bi bi-arrow-right ms-1"></i></span>
                <span wire:loading wire:target="verifyOtp"><span class="spinner-border spinner-border-sm me-2"></span>Checking…</span>
            </button>
        </form>

        {{-- TEMPORARY: sign in without the code --}}
        @if ($this->canSkipOtp())
            <button type="button" class="btn btn-outline-warning w-100 mt-2" wire:click="skipOtp" wire:loading.attr="disabled" wire:target="skipOtp">
                <span wire:loading.remove wire:target="skipOtp"><i class="bi bi-skip-forward me-1"></i> Skip code for now</span>
                <span wire:loading wire:target="skipOtp"><span class="spinner-border spinner-border-sm me-1"></span> Signing in…</span>
            </button>
            <p class="text-center text-muted mt-1 mb-0" style="font-size: .75rem;">Temporary — the code will be required again later.</p>
        @endif

        <div class="d-flex justify-content-between align-items-center mt-3 small">
            <button type="button" class="btn btn-link btn-sm text-secondary text-decoration-none px-0" wire:click="cancelOtp"><i class="bi bi-arrow-left me-1"></i> Use a different account</button>
            <button type="button" class="btn btn-link btn-sm fw-semibold text-cif-primary text-decoration-none px-0" wire:click="resendOtp" wire:loading.attr="disabled" wire:target="resendOtp">
                <span wire:loading.remove wire:target="resendOtp">Resend code</span>
                <span wire:loading wire:target="resendOtp"><span class="spinner-border spinner-border-sm me-1"></span> Sending…</span>
            </button>
        </div>
        <p class="text-center text-muted mt-3 mb-0" style="font-size: .75rem;">Can't find it? Check your spam or junk folder.</p>
    @else
    {{-- ============================ STEP 1 · E-MAIL + PASSWORD ============================ --}}
    <h2 class="h3 fw-bold text-cif-navy mb-1">Welcome back</h2>
    <p class="text-secondary small mb-4">Access your transactions, reviews and approvals.</p>

    @if (session('status'))
        <div class="alert alert-success small py-2"><i class="bi bi-check-circle me-1"></i> {{ session('status') }}</div>
    @endif
    @if (session('verification_error'))
        <div class="alert alert-warning small py-2"><i class="bi bi-exclamation-triangle me-1"></i> {{ session('verification_error') }}</div>
    @endif

    <form wire:submit="login" novalidate>
        {{-- Email --}}
        <div class="mb-3">
            <label for="login_email" class="form-label">Email address</label>
            <div class="input-group">
                <span class="input-group-text"><i class="bi bi-envelope"></i></span>
                <input id="login_email" type="email" wire:model="email" autocomplete="username"
                       placeholder="you@company.co.ls"
                       class="form-control @error('email') is-invalid @enderror">
            </div>
            @error('email') <div class="text-danger small mt-1">{{ $message }}</div> @enderror
            @if ($unverifiedEmail)
                <div class="mt-2 small">
                    <button type="button" class="btn btn-sm btn-outline-secondary" wire:click="resendVerification" wire:loading.attr="disabled" wire:target="resendVerification">
                        <span wire:loading.remove wire:target="resendVerification"><i class="bi bi-envelope me-1"></i> Resend verification e-mail</span>
                        <span wire:loading wire:target="resendVerification"><span class="spinner-border spinner-border-sm me-1"></span> Sending…</span>
                    </button>
                    @if ($resendMessage) <div class="text-secondary mt-1">{{ $resendMessage }}</div> @endif
                </div>
            @endif
        </div>

        {{-- Password --}}
        <div class="mb-3">
            <div class="d-flex justify-content-between align-items-center">
                <label for="login_password" class="form-label">Password</label>
                <a href="{{ \Illuminate\Support\Facades\Route::has('password.request') ? route('password.request') : url('/forgot-password') }}" class="small fw-semibold text-cif-primary text-decoration-none mb-1">Forgot password?</a>
            </div>
            <div class="input-group">
                <span class="input-group-text"><i class="bi bi-lock"></i></span>
                <input id="login_password" type="password" wire:model="password" autocomplete="current-password"
                       placeholder="••••••••"
                       class="form-control has-toggle @error('password') is-invalid @enderror">
                <button type="button" class="btn btn-toggle" data-toggle-password="login_password" aria-label="Show password">
                    <i class="bi bi-eye"></i>
                </button>
            </div>
            @error('password') <div class="text-danger small mt-1">{{ $message }}</div> @enderror
        </div>

        {{-- Remember --}}
        <div class="form-check mb-4">
            <input class="form-check-input" type="checkbox" id="remember" wire:model="remember">
            <label class="form-check-label small text-secondary" for="remember">Keep me signed in</label>
        </div>

        <button type="submit" class="btn btn-cif-primary btn-lg w-100" wire:loading.attr="disabled" wire:target="login">
            <span wire:loading.remove wire:target="login">{{ $this->otpEnabled() ? 'Continue' : 'Sign in' }} <i class="bi bi-arrow-right ms-1"></i></span>
            <span wire:loading wire:target="login"><span class="spinner-border spinner-border-sm me-2"></span>Checking…</span>
        </button>
        @if ($this->otpEnabled())
            <p class="text-center text-muted small mt-2 mb-0"><i class="bi bi-shield-lock me-1"></i> We'll e-mail you a code to finish signing in.</p>
        @endif
    </form>

    <p class="text-center small text-secondary mt-4 mb-2">
        New to the Facility?
        <button type="button" class="btn-link-cif accent" data-auth-show="register">Register your enterprise</button>
    </p>
    <p class="text-center text-muted mb-0" style="font-size: .75rem;">
        Committee, PMU and approver accounts are created by the System Administrator.
    </p>
    @endif
</div>