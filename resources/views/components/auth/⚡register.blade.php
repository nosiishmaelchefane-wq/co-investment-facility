<?php

use App\Models\User;
use Illuminate\Auth\Events\Registered;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Session;
use Illuminate\Validation\Rules;
use Livewire\Component;

new class extends Component
{
    public string $name = '';
    public string $enterprise_name = '';
    public string $email = '';
    public string $phone = '';
    public string $password = '';
    public string $password_confirmation = '';
    public bool $terms = false;

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
     * Handle an incoming registration request.
     */
    public function register(): void
    {
        $validated = $this->validate();

        $user = User::create([
            'name'            => $validated['name'],
            'enterprise_name' => $validated['enterprise_name'],
            'email'           => $validated['email'],
            'phone'           => $validated['phone'],
            'password'        => Hash::make($validated['password']),
        ]);

        // If you use spatie/laravel-permission:
        // $user->assignRole('enterprise');

        event(new Registered($user));

        Auth::login($user);

        Session::regenerate();

        $this->redirect(route('dashboard', absolute: false), navigate: false);
    }
};
?>

<div>
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
</div>