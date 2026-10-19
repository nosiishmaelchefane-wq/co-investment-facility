<?php

use App\Notifications\AccountSecurityChanged;
use Illuminate\Support\Str;
use Livewire\Component;

/*
| Account settings · two-step sign-in (6-digit code e-mailed after the password) — on/off for this user.
| Changing it asks for the password. Only takes effect while it is on for the platform (CIF_OTP_ENABLED=true),
| see User::requiresSignInCode() and the login component.
*/
new class extends Component
{
    public bool $enabled = true;

    /** The switch the user is asking for (shown in the confirm box) */
    public ?bool $pending = null;

    public string $password = '';

    public function mount(): void
    {
        $this->enabled = (bool) (auth()->user()->two_factor_enabled ?? true);
    }

    /** Switch clicked → ask for the password first (the switch itself doesn't move yet) */
    public function ask(): void
    {
        $this->pending = ! $this->enabled;
        $this->reset('password');
        $this->resetValidation();
    }

    public function cancel(): void
    {
        $this->pending = null;
        $this->reset('password');
    }

    public function confirm(): void
    {
        abort_if($this->pending === null, 400);

        $this->validate(['password' => ['required', 'current_password']], [
            'password.required'         => 'Enter your password.',
            'password.current_password' => 'That password is not correct.',
        ]);

        $u = auth()->user();
        $u->forceFill(['two_factor_enabled' => $this->pending, 'two_factor_changed_at' => now()])->save();

        $this->enabled = $this->pending;
        $this->pending = null;
        $this->reset('password');

        rescue(fn () => $u->notify(new AccountSecurityChanged('two_factor', ['on' => $this->enabled, 'ip' => request()->ip()])));

        $this->dispatch('two-factor-changed', enabled: $this->enabled);
        $this->dispatch('notify', type: $this->enabled ? 'success' : 'warning',
            message: $this->enabled ? 'Two-step sign-in is on.' : 'Two-step sign-in is off — only your password is needed to sign in.');
    }

    /** th***@cafi.org.ls */
    public function masked(): string
    {
        [$name, $domain] = array_pad(explode('@', (string) auth()->user()->email, 2), 2, '');

        return Str::substr($name, 0, 2).str_repeat('*', max(3, Str::length($name) - 2)).'@'.$domain;
    }
};
?>

@php
    $platformOn = (bool) config('cif.login.otp_enabled', false);
    $changedAt  = auth()->user()->two_factor_changed_at;
@endphp

<div class="as-card p-4">
    <div class="as-section-title">Two-step sign-in</div>
    <p class="small text-muted mb-4">When it is on, after your password we e-mail you a 6-digit code that you enter to finish signing in.</p>

    <div class="security-state {{ $enabled ? 'is-on' : 'is-off' }} d-flex align-items-start gap-3 mb-3">
        <i class="bi {{ $enabled ? 'bi-shield-check text-success' : 'bi-shield-exclamation text-warning' }} fs-3"></i>
        <div class="me-auto">
            <div class="fw-semibold">Two-step sign-in is <span class="{{ $enabled ? 'text-success' : 'text-warning' }}">{{ $enabled ? 'on' : 'off' }}</span></div>
            <div class="small text-muted">
                @if ($enabled)
                    Codes are sent to {{ $this->masked() }}.
                @else
                    Anyone with your password can sign in. We recommend turning it back on.
                @endif
                @if ($changedAt) Changed {{ $changedAt->format('d M Y') }}. @endif
            </div>
        </div>
    </div>

    <div class="as-row d-flex align-items-center gap-3 py-3">
        <div class="me-auto">
            <div class="fw-semibold small text-navy">Ask for an e-mailed code when I sign in</div>
            <div class="small text-muted">Turning this off makes your account easier to get into — for you and for anyone else.</div>
        </div>
        <div class="form-check form-switch form-switch-lg mb-0">
            {{-- The switch shows the saved state; clicking it opens the password check --}}
            <input class="form-check-input" type="checkbox" role="switch" id="tf-switch" aria-label="Two-step sign-in"
                   @checked($enabled) wire:click.prevent="ask" wire:key="tf-{{ $enabled ? 'on' : 'off' }}">
        </div>
    </div>

    @unless ($platformOn)
        <div class="callout callout-info small mt-3 mb-0">
            <i class="bi bi-info-circle me-1"></i>
            Two-step sign-in is temporarily switched off for everyone while the platform is being tested. Your choice here applies once it is switched back on.
        </div>
    @endunless

    {{-- ---------- Confirm with password (no Bootstrap JS needed) ---------- --}}
    @if ($pending !== null)
        <div class="modal d-block" tabindex="-1" role="dialog" aria-modal="true" style="background: rgba(13, 26, 58, .45);" wire:keydown.escape.window="cancel">
            <div class="modal-dialog modal-dialog-centered">
                <form class="modal-content" wire:submit="confirm">
                    <div class="modal-header">
                        <h5 class="modal-title"><i class="bi bi-shield-lock me-1"></i> Turn two-step sign-in {{ $pending ? 'on' : 'off' }}</h5>
                        <button type="button" class="btn-close" wire:click="cancel" aria-label="Close"></button>
                    </div>
                    <div class="modal-body">
                        <p class="small mb-3">To change how you sign in, confirm it's you by entering your password.</p>
                        @if (! $pending)
                            <div class="callout callout-warning small mb-3">
                                <i class="bi bi-exclamation-triangle me-1"></i> With two-step sign-in <strong>off</strong>, only your password protects your account.
                            </div>
                        @endif
                        <label class="form-label small" for="tf-password">Password <span class="text-danger">*</span></label>
                        <input type="password" class="form-control @error('password') is-invalid @enderror" id="tf-password" wire:model="password" autocomplete="current-password" autofocus>
                        @error('password') <div class="text-danger small mt-1">{{ $message }}</div> @enderror
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-outline-secondary" wire:click="cancel">Cancel</button>
                        <button type="submit" class="btn {{ $pending ? 'btn-cta' : 'btn-warning' }}" wire:loading.attr="disabled" wire:target="confirm">
                            <span wire:loading.remove wire:target="confirm"><i class="bi bi-check-lg me-1"></i> Turn {{ $pending ? 'on' : 'off' }}</span>
                            <span wire:loading wire:target="confirm"><span class="spinner-border spinner-border-sm me-1"></span> Saving…</span>
                        </button>
                    </div>
                </form>
            </div>
        </div>
    @endif
</div>