<?php

use App\Notifications\AccountSecurityChanged;
use Illuminate\Support\Facades\Notification;
use Illuminate\Validation\Rule;
use Livewire\Component;

/*
| Account settings · Profile — name, e-mail, phone, organisation.
| Changing the e-mail address asks for the current password and alerts the OLD address.
*/
new class extends Component
{
    /** Signed out in the meantime (session expired / signed out on another device) → back to the sign-in page */
    public function boot(): void
    {
        if (! auth()->check()) {
            $this->redirect(\Illuminate\Support\Facades\Route::has('login') ? route('login') : '/', navigate: false);
        }
    }

    public string $name = '';
    public string $email = '';
    public string $phone = '';
    public string $organisation = '';

    /** Only needed when the e-mail address changes */
    public string $password = '';

    public function mount(): void
    {
        $u = auth()->user();
        if (! $u) {
            return;
        }
        $this->name         = (string) $u->name;
        $this->email        = (string) $u->email;
        $this->phone        = (string) $u->phone;
        $this->organisation = (string) ($u->organisation ?: $u->enterprise_name);
    }

    public function emailChanged(): bool
    {
        return strcasecmp(trim($this->email), (string) auth()->user()?->email) !== 0;
    }

    public function save(): void
    {
        if (! auth()->check()) {
            return;   // signed out — boot() is sending them to the sign-in page
        }
        $u = auth()->user();

        $this->validate([
            'name'         => ['required', 'string', 'max:150'],
            'email'        => ['required', 'string', 'email', 'max:190', Rule::unique('users', 'email')->ignore($u->id)],
            'phone'        => ['nullable', 'string', 'max:30', 'regex:/^[0-9+\s()-]*$/'],
            'organisation' => ['nullable', 'string', 'max:190'],
            'password'     => $this->emailChanged() ? ['required', 'current_password'] : ['nullable'],
        ], [
            'password.required'         => 'Enter your current password to change your e-mail address.',
            'password.current_password' => 'That password is not correct.',
            'phone.regex'               => 'Use numbers only (spaces, + and brackets are fine).',
        ]);

        $oldEmail = $u->email;
        $changed  = $this->emailChanged();

        $u->forceFill([
            'name'  => trim($this->name),
            'email' => trim($this->email),
            'phone' => trim($this->phone) ?: null,
            // enterprises keep their enterprise name; staff use "organisation"
            $u->hasRole('enterprise') ? 'enterprise_name' : 'organisation' => trim($this->organisation) ?: null,
        ])->save();

        $note = '';
        if ($changed) {
            try {
                Notification::route('mail', $oldEmail)->notify(new AccountSecurityChanged('email', ['new' => $u->email, 'ip' => request()->ip()]));
                $note = ' A notice was sent to your old address.';
            } catch (\Throwable $e) {
                report($e);
            }
        }

        $this->reset('password');
        $this->dispatch('profile-updated', name: $u->name, email: $u->email);
        $this->dispatch('notify', type: 'success', message: 'Profile saved.'.$note);
    }
};
?>

<div>
    @guest
        {{-- Not signed in: show nothing (the page itself sends guests to the sign-in page) --}}
    @else
<form class="as-card p-4" wire:submit="save">
    <div class="as-section-title">Profile</div>
    <p class="small text-muted mb-4">Your name and contact details as they appear on the platform and in e-mails.</p>

    <div class="row g-3">
        <div class="col-md-6">
            <label class="form-label small" for="pf-name">Full name <span class="text-danger">*</span></label>
            <input type="text" class="form-control @error('name') is-invalid @enderror" id="pf-name" wire:model="name" autocomplete="name">
            @error('name') <div class="text-danger small mt-1">{{ $message }}</div> @enderror
        </div>
        <div class="col-md-6">
            <label class="form-label small" for="pf-email">E-mail address <span class="text-danger">*</span></label>
            <input type="email" class="form-control @error('email') is-invalid @enderror" id="pf-email" wire:model.live.debounce.500ms="email" autocomplete="email">
            @error('email') <div class="text-danger small mt-1">{{ $message }}</div> @else <div class="form-text small">You sign in with this address.</div> @enderror
        </div>

        @if ($this->emailChanged())
            <div class="col-md-6">
                <label class="form-label small" for="pf-password">Current password <span class="text-danger">*</span></label>
                <input type="password" class="form-control @error('password') is-invalid @enderror" id="pf-password" wire:model="password" autocomplete="current-password">
                @error('password') <div class="text-danger small mt-1">{{ $message }}</div> @else <div class="form-text small">Needed because you're changing your e-mail address.</div> @enderror
            </div>
            <div class="col-md-6 d-flex align-items-end">
                <div class="callout callout-warning small mb-0 w-100"><i class="bi bi-exclamation-triangle me-1"></i> From now on you'll sign in with the new address.</div>
            </div>
        @endif

        <div class="col-md-6">
            <label class="form-label small" for="pf-phone">Phone</label>
            <input type="tel" class="form-control @error('phone') is-invalid @enderror" id="pf-phone" wire:model="phone" placeholder="+266 5800 0000" autocomplete="tel">
            @error('phone') <div class="text-danger small mt-1">{{ $message }}</div> @enderror
        </div>
        <div class="col-md-6">
            <label class="form-label small" for="pf-org">{{ auth()->user()?->hasRole('enterprise') ? 'Enterprise name' : 'Organisation' }}</label>
            <input type="text" class="form-control @error('organisation') is-invalid @enderror" id="pf-org" wire:model="organisation" autocomplete="organization">
            @error('organisation') <div class="text-danger small mt-1">{{ $message }}</div> @enderror
        </div>
        <div class="col-md-6">
            <label class="form-label small">Role</label>
            <input type="text" class="form-control" value="{{ auth()->user()?->primary_role?->display_name ?? '—' }}" disabled>
            <div class="form-text small">Roles are changed by the System Administrator.</div>
        </div>
    </div>

    <div class="d-flex justify-content-end gap-2 mt-4">
        <button type="submit" class="btn btn-cta" wire:loading.attr="disabled" wire:target="save">
            <span wire:loading.remove wire:target="save"><i class="bi bi-check-lg me-1"></i> Save profile</span>
            <span wire:loading wire:target="save"><span class="spinner-border spinner-border-sm me-1"></span> Saving…</span>
        </button>
    </div>
</form>
    @endguest
</div>