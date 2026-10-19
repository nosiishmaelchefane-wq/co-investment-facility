<?php

use App\Notifications\AccountSecurityChanged;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\Rules\Password;
use Livewire\Component;

/*
| Account settings · change password.
| Optionally signs the user out everywhere else (other sessions deleted + Auth::logoutOtherDevices).
*/
new class extends Component
{
    public string $current_password = '';
    public string $password = '';
    public string $password_confirmation = '';
    public bool $logoutOthers = true;

    /** 0–4, shown as a bar while typing */
    public function strength(): int
    {
        $p = $this->password;
        if ($p === '') {
            return 0;
        }

        return min(4, (int) (strlen($p) >= 8) + (int) (strlen($p) >= 12)
            + (int) (preg_match('/[a-z]/', $p) && preg_match('/[A-Z]/', $p))
            + (int) preg_match('/\d/', $p) + (int) preg_match('/[^A-Za-z0-9]/', $p));
    }

    public function save(): void
    {
        $this->validate([
            'current_password' => ['required', 'current_password'],
            'password'         => ['required', 'confirmed', 'different:current_password', Password::min(8)->letters()->numbers()],
        ], [
            'current_password.current_password' => 'That password is not correct.',
            'password.different'                 => 'Choose a password different from your current one.',
        ]);

        $u = auth()->user();
        $u->forceFill(['password' => Hash::make($this->password)])->save();

        $others = 0;
        if ($this->logoutOthers) {
            if (config('session.driver') === 'database') {
                $others = DB::table(config('session.table', 'sessions'))->where('user_id', $u->id)->where('id', '!=', session()->getId())->delete();
            }
            rescue(fn () => Auth::logoutOtherDevices($this->password), report: false);   // also ends "keep me signed in" elsewhere
        }

        session()->put('password_hash_'.Auth::getDefaultDriver(), $u->getAuthPassword());   // keep THIS session valid

        rescue(fn () => $u->notify(new AccountSecurityChanged('password', ['ip' => request()->ip()])));

        $this->reset('current_password', 'password', 'password_confirmation');
        $this->dispatch('sessions-changed');
        $this->dispatch('notify', type: 'success', message: 'Password updated.'.($this->logoutOthers ? ' Other devices were signed out.' : ''));
    }
};
?>

@php
    $s      = $this->strength();
    $labels = ['', 'Weak', 'Fair', 'Good', 'Strong'];
    $colors = ['', 'bg-danger', 'bg-warning', 'bg-info', 'bg-success'];
@endphp

<form class="as-card p-4" wire:submit="save">
    <div class="as-section-title">Password</div>
    <p class="small text-muted mb-4">Use at least 8 characters with letters and numbers. You'll stay signed in on this device.</p>

    <div class="row g-3" style="max-width: 520px;">
        <div class="col-12">
            <label class="form-label small" for="pw-current">Current password <span class="text-danger">*</span></label>
            <input type="password" class="form-control @error('current_password') is-invalid @enderror" id="pw-current" wire:model="current_password" autocomplete="current-password">
            @error('current_password') <div class="text-danger small mt-1">{{ $message }}</div> @enderror
        </div>
        <div class="col-12">
            <label class="form-label small" for="pw-new">New password <span class="text-danger">*</span></label>
            <input type="password" class="form-control @error('password') is-invalid @enderror" id="pw-new" wire:model.live.debounce.300ms="password" autocomplete="new-password">
            @if ($s)
                <div class="progress mt-2" style="height: 5px;"><div class="progress-bar {{ $colors[$s] }}" style="width: {{ $s * 25 }}%"></div></div>
                <div class="small text-muted mt-1">Strength: {{ $labels[$s] }}</div>
            @endif
            @error('password') <div class="text-danger small mt-1">{{ $message }}</div> @enderror
        </div>
        <div class="col-12">
            <label class="form-label small" for="pw-confirm">Confirm new password <span class="text-danger">*</span></label>
            <input type="password" class="form-control" id="pw-confirm" wire:model="password_confirmation" autocomplete="new-password">
        </div>
        <div class="col-12">
            <div class="form-check">
                <input class="form-check-input" type="checkbox" id="pw-logout" wire:model="logoutOthers">
                <label class="form-check-label small" for="pw-logout">Sign me out on all other devices</label>
            </div>
        </div>
    </div>

    <div class="d-flex justify-content-end gap-2 mt-4">
        <button type="submit" class="btn btn-cta" wire:loading.attr="disabled" wire:target="save">
            <span wire:loading.remove wire:target="save"><i class="bi bi-key me-1"></i> Update password</span>
            <span wire:loading wire:target="save"><span class="spinner-border spinner-border-sm me-1"></span> Updating…</span>
        </button>
    </div>
</form>