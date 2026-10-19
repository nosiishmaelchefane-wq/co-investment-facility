<?php

use App\Models\User;
use Illuminate\Auth\Events\Registered;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Str;
use Illuminate\Validation\Rules;
use Livewire\Component;

/*
| Enterprise self-registration.
|   After registering, Laravel's default "Verify Email Address" e-mail is sent (Registered event → VerifyEmail notification).
|   The account can't sign in until the link in that e-mail has been clicked (see the login component).
*/
new class extends Component
{
    public string $name = '';
    public string $enterprise_name = '';
    public string $email = '';
    public string $phone = '';
    public string $password = '';
    public string $password_confirmation = '';
    public bool $terms = false;

    /** After registering: show "check your e-mail" instead of the form */
    public bool $registered = false;
    public string $registeredEmail = '';
    public ?string $resendMessage = null;

    /**
     * Validation rules (same as RegisteredUserController@store + enterprise fields).
     */
    protected function rules(): array
    {
        return [
            'name'            => ['required', 'string', 'max:255'],
            'enterprise_name' => ['required', 'string', 'max:255'],
            'email'           => ['required', 'string', 'lowercase', 'email', 'max:255', 'unique:' . User::class],
            'phone'           => ['required', 'string', 'max:30'],
            'password'        => ['required', 'confirmed', Rules\Password::defaults()],
            'terms'           => ['accepted'],
        ];
    }

    protected function messages(): array
    {
        return [
            'terms.accepted' => 'You must accept the Terms and Privacy Policy.',
        ];
    }

    /**
     * Create the account and send the verification e-mail. The user is NOT signed in.
     */
    public function register(): void
    {
        $validated = $this->validate();

        $user = User::create([
            'name'            => $validated['name'],
            'enterprise_name' => $validated['enterprise_name'],
            'organisation'    => $validated['enterprise_name'],
            'email'           => $validated['email'],
            'phone'           => $validated['phone'],
            'password'        => Hash::make($validated['password']),
            'status'          => User::STATUS_ACTIVE,
        ]);

        // Self-registered accounts are always enterprises
        $user->assignRole('enterprise');

        // Sends Laravel's default "Verify Email Address" e-mail (User implements MustVerifyEmail)
        try {
            event(new Registered($user));
        } catch (\Throwable $e) {
            report($e);
            $this->resendMessage = 'Your account was created, but the verification e-mail could not be sent. Use "Resend" below to try again.';
        }

        RateLimiter::hit($this->resendKey($user->email), 60);

        $this->registered      = true;
        $this->registeredEmail = $user->email;
        $this->reset('name', 'enterprise_name', 'email', 'phone', 'password', 'password_confirmation', 'terms');
    }

    /** Send the verification e-mail again (at most once a minute) */
    public function resendVerification(): void
    {
        $key = $this->resendKey($this->registeredEmail);

        if (RateLimiter::tooManyAttempts($key, 1)) {
            $this->resendMessage = 'Please wait '.RateLimiter::availableIn($key).' seconds before requesting another e-mail.';
            return;
        }
        RateLimiter::hit($key, 60);

        $user = User::where('email', $this->registeredEmail)->first();
        if ($user && ! $user->hasVerifiedEmail()) {
            try {
                $user->sendEmailVerificationNotification();
                $this->resendMessage = 'A new verification link has been sent to '.$this->registeredEmail.'.';
            } catch (\Throwable $e) {
                report($e);
                $this->resendMessage = 'The e-mail could not be sent right now. Please try again in a few minutes.';
            }
        } else {
            $this->resendMessage = 'This e-mail address is already verified — you can sign in.';
        }
    }

    protected function resendKey(string $email): string
    {
        return 'verify-resend:'.Str::lower($email);
    }
};
?>

<div>
    @if ($registered)
        {{-- ============ Check your e-mail ============ --}}
        <div class="text-center">
            <div class="mb-3"><i class="bi bi-envelope-check text-cif-primary" style="font-size: 3rem;"></i></div>
            <h2 class="h3 fw-bold text-cif-navy mb-2">Check your e-mail</h2>
            <p class="text-secondary small mb-1">We've sent a verification link to</p>
            <p class="fw-semibold mb-3">{{ $registeredEmail }}</p>
            <p class="text-secondary small mb-4">Click the link in the e-mail to activate your account, then sign in. If you can't find it, check your spam or junk folder.</p>

            @if ($resendMessage)
                <div class="alert alert-info small py-2">{{ $resendMessage }}</div>
            @endif

            <button type="button" class="btn btn-outline-secondary w-100 mb-2" wire:click="resendVerification" wire:loading.attr="disabled" wire:target="resendVerification">
                <span wire:loading.remove wire:target="resendVerification"><i class="bi bi-arrow-repeat me-1"></i> Resend verification e-mail</span>
                <span wire:loading wire:target="resendVerification"><span class="spinner-border spinner-border-sm me-2"></span>Sending…</span>
            </button>
            <button type="button" class="btn btn-cif-accent w-100" data-auth-show="login">Go to sign in <i class="bi bi-arrow-right ms-1"></i></button>
        </div>
    @else
    <h2 class="h3 fw-bold text-cif-navy mb-1">Register your enterprise</h2>
    <p class="text-secondary small mb-4">Create an account to submit a Transaction EOI or join matchmaking.</p>

    <form wire:submit="register" novalidate>
        {{-- Full name --}}
        <div class="mb-3">
            <label for="reg_name" class="form-label">Full name</label>
            <div class="input-group">
                <span class="input-group-text"><i class="bi bi-person"></i></span>
                <input id="reg_name" type="text" wire:model="name" autocomplete="name"
                       placeholder="Your full name"
                       class="form-control @error('name') is-invalid @enderror">
            </div>
            @error('name') <div class="text-danger small mt-1">{{ $message }}</div> @enderror
        </div>

        {{-- Enterprise name --}}
        <div class="mb-3">
            <label for="reg_enterprise" class="form-label">Enterprise name</label>
            <div class="input-group">
                <span class="input-group-text"><i class="bi bi-building"></i></span>
                <input id="reg_enterprise" type="text" wire:model="enterprise_name" autocomplete="organization"
                       placeholder="Registered business name"
                       class="form-control @error('enterprise_name') is-invalid @enderror">
            </div>
            @error('enterprise_name') <div class="text-danger small mt-1">{{ $message }}</div> @enderror
        </div>

        {{-- Email + Phone --}}
        <div class="row g-3 mb-3">
            <div class="col-sm-6">
                <label for="reg_email" class="form-label">Email</label>
                <input id="reg_email" type="email" wire:model="email" autocomplete="email"
                       placeholder="you@company.co.ls"
                       class="form-control @error('email') is-invalid @enderror">
                @error('email') <div class="text-danger small mt-1">{{ $message }}</div> @enderror
            </div>
            <div class="col-sm-6">
                <label for="reg_phone" class="form-label">Phone</label>
                <input id="reg_phone" type="tel" wire:model="phone" autocomplete="tel"
                       placeholder="+266 5xxx xxxx"
                       class="form-control @error('phone') is-invalid @enderror">
                @error('phone') <div class="text-danger small mt-1">{{ $message }}</div> @enderror
            </div>
        </div>

        {{-- Password + Confirm --}}
        <div class="row g-3 mb-3">
            <div class="col-sm-6">
                <label for="reg_password" class="form-label">Password</label>
                <div class="input-group">
                    <input id="reg_password" type="password" wire:model="password" autocomplete="new-password"
                           placeholder="Min. 8 characters"
                           class="form-control has-toggle @error('password') is-invalid @enderror">
                    <button type="button" class="btn btn-toggle" data-toggle-password="reg_password" aria-label="Show password">
                        <i class="bi bi-eye"></i>
                    </button>
                </div>
            </div>
            <div class="col-sm-6">
                <label for="reg_password_confirmation" class="form-label">Confirm</label>
                <input id="reg_password_confirmation" type="password" wire:model="password_confirmation" autocomplete="new-password"
                       placeholder="Repeat password" class="form-control">
            </div>
            @error('password') <div class="col-12 text-danger small mt-1">{{ $message }}</div> @enderror
        </div>

        {{-- Terms --}}
        <div class="form-check mb-4">
            <input class="form-check-input @error('terms') is-invalid @enderror" type="checkbox" id="terms" wire:model="terms">
            <label class="form-check-label small text-secondary" for="terms">
                I agree to the <a href="#" class="fw-semibold text-cif-primary">Terms</a>
                and <a href="#" class="fw-semibold text-cif-primary">Privacy Policy</a>.
            </label>
            @error('terms') <div class="text-danger small mt-1">{{ $message }}</div> @enderror
        </div>

        <button type="submit" class="btn btn-cif-accent btn-lg w-100" wire:loading.attr="disabled" wire:target="register">
            <span wire:loading.remove wire:target="register">Create account <i class="bi bi-arrow-right ms-1"></i></span>
            <span wire:loading wire:target="register"><span class="spinner-border spinner-border-sm me-2"></span>Creating account…</span>
        </button>
    </form>

    <p class="text-center small text-secondary mt-4 mb-0">
        Already have an account?
        <button type="button" class="btn-link-cif" data-auth-show="login">Sign in</button>
    </p>
    @endif
</div>