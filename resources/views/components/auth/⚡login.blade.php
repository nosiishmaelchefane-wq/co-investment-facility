<?php

use Illuminate\Auth\Events\Lockout;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Facades\Session;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use Livewire\Attributes\Validate;
use Livewire\Component;

new class extends Component
{
    #[Validate('required|string|email')]
    public string $email = '';

    #[Validate('required|string')]
    public string $password = '';

    public bool $remember = false;

    /**
     * Handle an incoming authentication request.
     * (Same logic as AuthenticatedSessionController@store + LoginRequest::authenticate)
     */
    public function login(): void
    {
        $this->validate();

        $this->ensureIsNotRateLimited();

        if (! Auth::attempt(['email' => $this->email, 'password' => $this->password], $this->remember)) {
            RateLimiter::hit($this->throttleKey());

            throw ValidationException::withMessages([
                'email' => trans('auth.failed'),
            ]);
        }

        RateLimiter::clear($this->throttleKey());

        Session::regenerate();

        $this->redirectIntended(default: route('dashboard', absolute: false), navigate: false);
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
    <h2 class="h3 fw-bold text-cif-navy mb-1">Welcome back</h2>
    <p class="text-secondary small mb-4">Access your transactions, reviews and approvals.</p>

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
        </div>

        {{-- Password --}}
        <div class="mb-3">
            <div class="d-flex justify-content-between align-items-center">
                <label for="login_password" class="form-label">Password</label>
                <a href="#" class="small fw-semibold text-cif-primary text-decoration-none mb-1">Forgot password?</a>
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
            <span wire:loading.remove wire:target="login">Sign in <i class="bi bi-arrow-right ms-1"></i></span>
            <span wire:loading wire:target="login"><span class="spinner-border spinner-border-sm me-2"></span>Signing in…</span>
        </button>
    </form>

    <p class="text-center small text-secondary mt-4 mb-2">
        New to the Facility?
        <button type="button" class="btn-link-cif accent" data-auth-show="register">Register your enterprise</button>
    </p>
    <p class="text-center text-muted mb-0" style="font-size: .75rem;">
        Committee, PMU and approver accounts are created by the System Administrator.
    </p>
</div>