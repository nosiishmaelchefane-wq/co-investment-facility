<?php

use App\Models\Role;
use App\Models\User;
use App\Notifications\AccountAccessLink;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Rules\Password;
use Livewire\Attributes\Computed;
use Livewire\Attributes\On;
use Livewire\Component;

new class extends Component
{
    /** 'new' | 'edit' */
    public string $mode = 'new';

    public ?int $userId = null;

    /** Editing your own account (can't change own role / status) */
    public bool $isSelf = false;

    /** Status of the user being edited (to show "invitation pending") */
    public ?string $currentStatus = null;

    public bool $showDrawer = false;

    public array $form = [];

    public function mount(): void
    {
        $this->form = $this->blankForm();
    }

    #[Computed]
    public function roles()
    {
        return Role::orderBy('id')->get();
    }

    /* ---------------- Listeners ---------------- */

    #[On('open-user')]
    public function openNew(): void
    {
        $this->authorizeAccess();
        $this->resetDrawer();
        $this->showDrawer = true;
    }

    #[On('edit-user')]
    public function openEdit(int $id): void
    {
        $this->authorizeAccess();
        $this->resetDrawer();

        $user = User::with('roles:id,name')->findOrFail($id);

        $this->mode          = 'edit';
        $this->userId        = $user->id;
        $this->isSelf        = $user->is(auth()->user());
        $this->currentStatus = $user->status;
        $this->form          = array_merge($this->blankForm(), [
            'name'         => $user->name,
            'email'        => $user->email,
            'phone'        => $user->phone ?? '',
            'role'         => $user->roles->first()?->name ?? '',
            'organisation' => $user->organisation_name ?? '',
            'status'       => $user->isActive() ? 'active' : 'inactive',
        ]);
        $this->showDrawer = true;
    }

    public function closeDrawer(): void
    {
        $this->showDrawer = false;
    }

    /* ---------------- Save ---------------- */

    public function save(): void
    {
        $this->authorizeAccess();

        $this->form['email'] = Str::lower(trim($this->form['email']));
        $this->validate();

        $existing = $this->userId ? User::with('roles')->findOrFail($this->userId) : null;

        // ---- Safety checks ------------------------------------------------
        if ($existing && $this->isSelf) {
            // Your own role and status can't be changed here (prevents locking yourself out)
            $this->form['role']   = $existing->roles->first()?->name ?? $this->form['role'];
            $this->form['status'] = 'active';
        }

        if ($existing && $existing->hasRole(Role::ADMIN)
            && ($this->form['role'] !== Role::ADMIN || $this->form['status'] === 'inactive')
            && User::activeAdminCount(exceptId: $existing->id) === 0) {
            $this->addError('form.role', 'This is the last active System Administrator — add another administrator first.');
            return;
        }

        // ---- Save ---------------------------------------------------------
        $sendInvite = false;

        $user = DB::transaction(function () use ($existing, &$sendInvite) {
            $data = [
                'name'         => trim($this->form['name']),
                'email'        => $this->form['email'],
                'phone'        => trim($this->form['phone']) ?: null,
                'organisation' => trim($this->form['organisation']) ?: null,
            ];

            // enterprise_name is only touched for enterprise accounts (kept as-is for staff)
            if ($this->form['role'] === 'enterprise') {
                $data['enterprise_name'] = $data['organisation'];
            }

            if ($existing) {
                $data['status'] = $this->form['status'] === 'inactive'
                    ? User::STATUS_INACTIVE
                    : ($existing->status === User::STATUS_INVITED ? User::STATUS_INVITED : User::STATUS_ACTIVE);

                $existing->update($data);
                $user = $existing;
            } elseif ($this->form['access'] === 'invite') {
                $user = User::create($data + [
                    'password'   => Str::random(40),           // unusable until they set their own
                    'status'     => User::STATUS_INVITED,
                    'invited_at' => now(),
                ]);
                $sendInvite = true;
            } else {
                $user = User::create($data + [
                    'password' => $this->form['password'],      // hashed by the model cast
                    'status'   => $this->form['status'] === 'inactive' ? User::STATUS_INACTIVE : User::STATUS_ACTIVE,
                ]);
            }

            $user->syncRoles([$this->form['role']]);

            return $user;
        });

        // ---- Email (after the transaction, so a mail error never loses the user) ----
        $message = $this->mode === 'new' ? "Account created for {$user->name}." : "{$user->name} has been updated.";

        if ($sendInvite) {
            try {
                $user->notify(new AccountAccessLink('invite'));
                $message = "Invitation sent to {$user->email}.";
            } catch (\Throwable $e) {
                report($e);
                $this->dispatch('notify', type: 'error', message: "User created, but the invitation email failed. Use \"Resend invitation\" once mail is set up.");
                $message = null;
            }
        }

        $this->dispatch('user-saved', id: $user->id);
        if ($message) {
            $this->dispatch('notify', type: 'success', message: $message);
        }

        $this->closeDrawer();
    }

    protected function rules(): array
    {
        $isNewWithPassword = $this->mode === 'new' && $this->form['access'] === 'password';

        return [
            'form.name'         => ['required', 'string', 'max:255'],
            'form.email'        => ['required', 'email', 'max:255', Rule::unique('users', 'email')->ignore($this->userId)],
            'form.phone'        => ['nullable', 'string', 'max:30', Rule::unique('users', 'phone')->ignore($this->userId)],
            'form.role'         => ['required', Rule::exists(config('permission.table_names.roles'), 'name')],
            'form.organisation' => [$this->form['role'] === 'enterprise' ? 'required' : 'nullable', 'string', 'max:255'],
            'form.access'       => ['required', 'in:invite,password'],
            'form.status'       => ['required', 'in:active,inactive'],
            'form.password'     => $isNewWithPassword ? ['required', 'confirmed', Password::defaults()] : ['nullable'],
        ];
    }

    protected function messages(): array
    {
        return [
            'form.email.unique' => 'Another account already uses this email.',
            'form.phone.unique' => 'Another account already uses this phone number.',
        ];
    }

    protected function validationAttributes(): array
    {
        return [
            'form.name'         => 'full name',
            'form.email'        => 'email',
            'form.phone'        => 'phone',
            'form.role'         => 'role',
            'form.organisation' => $this->form['role'] === 'enterprise' ? 'enterprise name' : 'organisation',
            'form.password'     => 'password',
        ];
    }

    /* ---------------- Helpers ---------------- */

    protected function blankForm(): array
    {
        return [
            'name'                  => '',
            'email'                 => '',
            'phone'                 => '',
            'role'                  => '',
            'organisation'          => '',
            'access'                => 'invite',     // invite | password  (new users only)
            'password'              => '',
            'password_confirmation' => '',
            'status'                => 'active',     // active | inactive
        ];
    }

    protected function resetDrawer(): void
    {
        $this->resetValidation();
        $this->mode          = 'new';
        $this->userId        = null;
        $this->isSelf        = false;
        $this->currentStatus = null;
        $this->form          = $this->blankForm();
    }

    protected function authorizeAccess(): void
    {
        abort_unless(auth()->user()?->can('users.manage'), 403);
    }
};
?>

{{--
    User create / edit drawer — Livewire 4 + Spatie roles.
    Open:  $dispatch('open-user')          Edit: $dispatch('edit-user', { id: 5 })
--}}
<div>
@if ($showDrawer)
    @php
        $isEnterprise = $form['role'] === 'enterprise';
    @endphp

    <div class="drawer-overlay drawer-fade" wire:click="closeDrawer"></div>

    <div class="drawer drawer-md drawer-panel drawer-enter" role="dialog" aria-modal="true" aria-labelledby="userDrawerLabel"
         wire:keydown.escape.window="closeDrawer">

        {{-- Header --}}
        <div class="offcanvas-header drawer-header">
            <div>
                <div class="drawer-eyebrow">User management</div>
                <h5 class="offcanvas-title" id="userDrawerLabel">
                    {{ $mode === 'edit' ? 'Edit user · '.$form['name'] : 'Add a new user' }}
                </h5>
            </div>
            <button type="button" class="btn-close btn-close-white" wire:click="closeDrawer" aria-label="Close"></button>
        </div>

        <form wire:submit.prevent="save" class="form-modern d-flex flex-column flex-grow-1 overflow-hidden" novalidate>
            <div class="offcanvas-body drawer-body">

                @if ($isSelf)
                    <div class="callout small mb-3">
                        <i class="bi bi-info-circle me-1"></i> This is your own account — your role and status can't be changed here.
                    </div>
                @endif

                {{-- 1. Personal details --}}
                <div class="form-section-head">
                    <span class="q-badge">1</span>
                    <div><h6>Personal details</h6><p>Who the account belongs to.</p></div>
                </div>
                <div class="row g-3">
                    <div class="col-12">
                        <label class="form-label" for="u_name">Full name <span class="req">*</span></label>
                        <input type="text" id="u_name" wire:model="form.name" class="form-control @error('form.name') is-invalid @enderror">
                        @error('form.name') <div class="invalid-feedback">{{ $message }}</div> @enderror
                    </div>
                    <div class="col-md-7">
                        <label class="form-label" for="u_email">Email <span class="req">*</span></label>
                        <input type="email" id="u_email" wire:model="form.email" class="form-control @error('form.email') is-invalid @enderror">
                        @error('form.email') <div class="invalid-feedback">{{ $message }}</div> @enderror
                    </div>
                    <div class="col-md-5">
                        <label class="form-label" for="u_phone">Phone <span class="opt">(optional)</span></label>
                        <input type="tel" id="u_phone" wire:model="form.phone" placeholder="+266 5xxx xxxx" class="form-control @error('form.phone') is-invalid @enderror">
                        @error('form.phone') <div class="invalid-feedback">{{ $message }}</div> @enderror
                    </div>
                </div>

                {{-- 2. Role --}}
                <div class="form-section-head mt-4">
                    <span class="q-badge">2</span>
                    <div><h6>Role <span class="req">*</span></h6><p>Decides what this user can see and do.</p></div>
                </div>
                <div class="choice-group cols-2 @error('form.role') is-invalid @enderror">
                    @foreach ($this->roles as $r)
                        <label class="choice choice--card" wire:key="role-{{ $r->id }}">
                            <input type="radio" wire:model.live="form.role" value="{{ $r->name }}" @disabled($isSelf)>
                            <span class="choice-box">
                                <span class="choice-icon"><i class="bi {{ $r->icon }}"></i></span>
                                <span>
                                    <span class="d-block fw-semibold">{{ $r->display_name }}</span>
                                    @if ($r->description)
                                        <span class="d-block small text-muted lh-sm mt-1">{{ $r->description }}</span>
                                    @endif
                                </span>
                            </span>
                        </label>
                    @endforeach
                </div>
                @error('form.role') <div class="text-danger small mt-1">{{ $message }}</div> @enderror

                <div class="mt-3">
                    <label class="form-label" for="u_org">
                        {{ $isEnterprise ? 'Enterprise name' : 'Organisation / unit' }}
                        @if ($isEnterprise) <span class="req">*</span> @else <span class="opt">(optional)</span> @endif
                    </label>
                    <input type="text" id="u_org" wire:model="form.organisation"
                           placeholder="{{ $isEnterprise ? 'e.g. Maluti Fresh Foods' : 'e.g. LEHSFF PMU' }}"
                           class="form-control @error('form.organisation') is-invalid @enderror">
                    @error('form.organisation') <div class="invalid-feedback">{{ $message }}</div> @enderror
                </div>

                @if ($form['role'] === 'committee')
                    <div class="callout small mt-3">
                        <i class="bi bi-info-circle me-1"></i>
                        The Investment Committee has <strong>3 fixed members</strong>. Members declare conflicts of interest per transaction at Gate 3.
                    </div>
                @endif

                {{-- 3. Sign-in (new users only) --}}
                @if ($mode === 'new')
                    <div class="form-section-head mt-4">
                        <span class="q-badge">3</span>
                        <div><h6>How will they sign in?</h6><p>Invite them by email or set a temporary password.</p></div>
                    </div>
                    <div class="choice-group cols-2">
                        <label class="choice choice--card">
                            <input type="radio" wire:model.live="form.access" value="invite">
                            <span class="choice-box">
                                <span class="choice-icon"><i class="bi bi-envelope"></i></span>
                                <span>
                                    <span class="d-block fw-semibold">Send invitation</span>
                                    <span class="d-block small text-muted lh-sm mt-1">They set their own password from the email link ({{ \App\Models\User::SETUP_LINK_HOURS }} hours).</span>
                                </span>
                            </span>
                        </label>
                        <label class="choice choice--card">
                            <input type="radio" wire:model.live="form.access" value="password">
                            <span class="choice-box">
                                <span class="choice-icon"><i class="bi bi-key"></i></span>
                                <span>
                                    <span class="d-block fw-semibold">Set temporary password</span>
                                    <span class="d-block small text-muted lh-sm mt-1">Share it with them; they change it after signing in.</span>
                                </span>
                            </span>
                        </label>
                    </div>

                    @if ($form['access'] === 'password')
                        <div class="row g-3 mt-1">
                            <div class="col-md-6">
                                <label class="form-label" for="u_pw">Temporary password <span class="req">*</span></label>
                                <input type="password" id="u_pw" wire:model="form.password" autocomplete="new-password" class="form-control @error('form.password') is-invalid @enderror">
                                @error('form.password') <div class="invalid-feedback">{{ $message }}</div> @enderror
                            </div>
                            <div class="col-md-6">
                                <label class="form-label" for="u_pw2">Confirm password <span class="req">*</span></label>
                                <input type="password" id="u_pw2" wire:model="form.password_confirmation" autocomplete="new-password" class="form-control">
                            </div>
                        </div>
                    @endif
                @endif

                {{-- 4. Status (edit, or new with a password) --}}
                @if ($mode === 'edit' || $form['access'] === 'password')
                    <div class="form-section-head mt-4">
                        <span class="q-badge">{{ $mode === 'edit' ? 3 : 4 }}</span>
                        <div><h6>Account status</h6><p>Inactive users cannot sign in.</p></div>
                    </div>
                    <div class="choice-group cols-2">
                        <label class="choice">
                            <input type="radio" wire:model="form.status" value="active" @disabled($isSelf)>
                            <span class="choice-box"><span class="choice-radio"></span> Active</span>
                        </label>
                        <label class="choice">
                            <input type="radio" wire:model="form.status" value="inactive" @disabled($isSelf)>
                            <span class="choice-box"><span class="choice-radio"></span> Inactive</span>
                        </label>
                    </div>

                    @if ($mode === 'edit')
                        <p class="small text-muted mt-2 mb-0">
                            @if ($currentStatus === \App\Models\User::STATUS_INVITED)
                                <i class="bi bi-envelope me-1"></i> Invitation pending — use <strong>Resend invitation</strong> in the table if they didn't get it.
                            @else
                                <i class="bi bi-key me-1"></i> To change their password, use <strong>Send password reset</strong> in the table.
                            @endif
                        </p>
                    @endif
                @endif
            </div>

            {{-- Footer --}}
            <div class="drawer-footer">
                <button type="button" class="btn btn-outline-secondary" wire:click="closeDrawer">Cancel</button>
                <button type="submit" class="btn btn-brand ms-auto" wire:loading.attr="disabled" wire:target="save">
                    <span wire:loading.remove wire:target="save">
                        <i class="bi {{ $mode === 'new' ? 'bi-person-plus' : 'bi-check-lg' }} me-1"></i>
                        {{ $mode === 'new' ? ($form['access'] === 'invite' ? 'Create & send invite' : 'Create account') : 'Save changes' }}
                    </span>
                    <span wire:loading wire:target="save"><span class="spinner-border spinner-border-sm me-1"></span> Saving…</span>
                </button>
            </div>
        </form>
    </div>
@endif
</div>