<?php

use App\Support\UserAgent;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Livewire\Attributes\Computed;
use Livewire\Attributes\On;
use Livewire\Component;

/*
| Account settings · signed-in devices — read from the database session table (SESSION_DRIVER=database).
|   Device + browser from the user agent, IP address, last active. (No town: that would need a GeoIP lookup.)
|   "Sign out" deletes that session row. "Sign out all other devices" asks for the password and also ends
|   "keep me signed in" logins elsewhere (Auth::logoutOtherDevices).
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

    public bool $confirming = false;
    public string $password = '';

    protected function table(): string
    {
        return config('session.table', 'sessions');
    }

    #[Computed]
    public function supported(): bool
    {
        return config('session.driver') === 'database';
    }

    #[On('sessions-changed')]
    public function refresh(): void
    {
        unset($this->sessions);
    }

    #[Computed]
    public function sessions()
    {
        if (! $this->supported) {
            return collect();
        }

        $current = session()->getId();

        return DB::table($this->table())
            ->where('user_id', auth()->id())
            ->orderByDesc('last_activity')
            ->get(['id', 'ip_address', 'user_agent', 'last_activity'])
            ->map(fn ($s) => (object) [
                'id'      => $s->id,
                'ip'      => $s->ip_address ?: 'unknown IP',
                'device'  => UserAgent::describe($s->user_agent),
                'active'  => Carbon::createFromTimestamp($s->last_activity),
                'current' => $s->id === $current,
            ])
            ->sortByDesc('current')->values();
    }

    public function signOut(string $id): void
    {
        if (! auth()->check()) {
            return;   // signed out — boot() is sending them to the sign-in page
        }
        abort_if($id === session()->getId(), 400);   // use the normal Logout for this device

        DB::table($this->table())->where('user_id', auth()->id())->where('id', $id)->delete();

        unset($this->sessions);
        $this->dispatch('notify', type: 'success', message: 'That device was signed out.');
    }

    public function signOutOthers(): void
    {
        if (! auth()->check()) {
            return;   // signed out — boot() is sending them to the sign-in page
        }
        $this->validate(['password' => ['required', 'current_password']], [
            'password.required'         => 'Enter your password.',
            'password.current_password' => 'That password is not correct.',
        ]);

        $n = DB::table($this->table())->where('user_id', auth()->id())->where('id', '!=', session()->getId())->delete();
        Auth::logoutOtherDevices($this->password);

        $this->reset('password', 'confirming');
        unset($this->sessions);
        $this->dispatch('notify', type: 'success', message: $n ? "Signed out of {$n} other ".\Illuminate\Support\Str::plural('device', $n).'.' : 'Any other "keep me signed in" devices were signed out.');
    }
};
?>

<div>
    @guest
        {{-- Not signed in: show nothing (the page itself sends guests to the sign-in page) --}}
    @else
<div class="as-card p-4">
    <div class="as-section-title">Signed-in devices</div>
    <p class="small text-muted mb-3">Where your account is signed in right now. Sign out anything you don't recognise and change your password.</p>

    @if (! $this->supported)
        <div class="callout callout-warning small mb-0">
            <i class="bi bi-exclamation-triangle me-1"></i>
            Device list isn't available: sessions are not stored in the database. Set <code>SESSION_DRIVER=database</code> in <code>.env</code>
            (run <code>php artisan session:table</code> and <code>php artisan migrate</code> if the table doesn't exist).
        </div>
    @else
        @forelse ($this->sessions as $s)
            <div class="as-row d-flex align-items-center gap-3 py-3" wire:key="sess-{{ $s->id }}">
                <span class="device-icon"><i class="bi {{ $s->device['icon'] }}"></i></span>
                <div class="me-auto">
                    <div class="fw-semibold small text-navy">
                        {{ $s->device['label'] }}
                        @if ($s->current) <span class="badge text-bg-success ms-1">This device</span> @endif
                    </div>
                    <div class="small text-muted" title="Last active {{ $s->active->format('d M Y H:i') }}">
                        {{ $s->ip }} · {{ $s->current ? 'active now' : $s->active->diffForHumans() }}
                    </div>
                </div>
                @unless ($s->current)
                    <button type="button" class="btn btn-sm btn-outline-danger" wire:click="signOut('{{ $s->id }}')" wire:confirm="Sign out {{ $s->device['label'] }}?">Sign out</button>
                @endunless
            </div>
        @empty
            <p class="small text-muted mb-0">No devices found.</p>
        @endforelse

        @if ($this->sessions->where('current', false)->isNotEmpty() || $confirming)
            <div class="mt-3">
                @if (! $confirming)
                    <div class="d-flex justify-content-end">
                        <button type="button" class="btn btn-outline-danger" wire:click="$set('confirming', true)"><i class="bi bi-box-arrow-right me-1"></i> Sign out all other devices</button>
                    </div>
                @else
                    <form class="p-3 rounded-3" style="background: #fff7ed; border: 1px solid #fed7aa;" wire:submit="signOutOthers">
                        <label class="form-label small fw-semibold" for="ss-password">Enter your password to sign out everywhere else</label>
                        <div class="d-flex flex-wrap gap-2">
                            <input type="password" class="form-control @error('password') is-invalid @enderror" style="max-width: 280px;" id="ss-password" wire:model="password" autocomplete="current-password" autofocus>
                            <button type="submit" class="btn btn-danger" wire:loading.attr="disabled" wire:target="signOutOthers">
                                <span wire:loading.remove wire:target="signOutOthers">Sign out others</span>
                                <span wire:loading wire:target="signOutOthers"><span class="spinner-border spinner-border-sm"></span></span>
                            </button>
                            <button type="button" class="btn btn-outline-secondary" wire:click="$set('confirming', false)">Cancel</button>
                        </div>
                        @error('password') <div class="text-danger small mt-1">{{ $message }}</div> @enderror
                    </form>
                @endif
            </div>
        @endif
    @endif
</div>
    @endguest
</div>