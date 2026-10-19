<?php

use App\Models\EnterpriseInvestment;
use App\Models\EnterpriseProfile;
use App\Models\ExpressionOfInterest;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\Rule;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Locked;
use Livewire\Component;
use Livewire\WithFileUploads;

/*
| Application tab · Investments
|   Every investment made into the enterprise (declared on its profile or recorded here), grouped by investor:
|   how many times each investor has invested, how much, when, and where else on the platform they invest.
|   Reviewers (gate1.decide / gate2.assess) record, edit, verify and reject investments and use the history at Gate 1.
|   Embedded in the application page:  <livewire:expression-of-interest.investments.investment-history :eoi-id="$eoi->id" />
*/
new class extends Component
{
    use WithFileUploads;

    #[Locked]
    public int $eoiId;

    /** Show only one investor's investments (investor key, '' = all) */
    public string $investor = '';

    /** Add / edit form */
    public bool $invOpen = false;
    public ?int $invEditingId = null;
    public array $inv = [];
    public $invProof = null;

    /** Verify / reject */
    public ?int $reviewingId = null;
    public string $reviewStatus = '';
    public string $reviewNote = '';

    public function mount(int $eoiId): void
    {
        $eoi = ExpressionOfInterest::findOrFail($eoiId);
        abort_unless($eoi->isViewableBy(auth()->user()), 403);

        $this->eoiId = $eoi->id;
        $this->inv   = $this->blank();
    }

    /* ---------------- Data ---------------- */

    #[Computed]
    public function eoi(): ExpressionOfInterest
    {
        return ExpressionOfInterest::findOrFail($this->eoiId);
    }

    #[Computed]
    public function profile(): ?EnterpriseProfile
    {
        return EnterpriseProfile::where('user_id', $this->eoi->user_id)->first();
    }

    #[Computed]
    public function canManage(): bool
    {
        $me = auth()->user();

        return $this->eoi->user_id !== $me->id && $me->canAny(['gate1.decide', 'gate2.assess']);
    }

    #[Computed]
    public function investments()
    {
        return $this->profile
            ? $this->profile->investments()->with(['reviewer:id,name', 'recorder:id,name'])->get()
            : collect();
    }

    #[Computed]
    public function groups()
    {
        return EnterpriseInvestment::byInvestor($this->investments);
    }

    /** investorKey => the investor's record across every enterprise in the facility (see EnterpriseInvestment::facilityRecord) */
    #[Computed]
    public function facility(): array
    {
        if (! $this->profile) {
            return [];
        }

        $keys = $this->groups->pluck('key')
            ->merge(collect($this->profile->investorRows())->map(fn ($r) => EnterpriseInvestment::investorKey($r['name'] ?? '')))
            ->all();

        return EnterpriseInvestment::facilityRecord($keys, $this->profile->id);
    }

    /* ---------------- Filter ---------------- */

    public function showInvestor(string $key): void
    {
        $this->investor = $this->investor === $key ? '' : $key;
    }

    /* ---------------- Record / edit ---------------- */

    protected function blank(): array
    {
        return ['investor_name' => '', 'investor_type' => '', 'investor_country' => '', 'amount' => '', 'invested_on' => '',
                'instrument' => '', 'round_label' => '', 'notes' => ''];
    }

    public function newInvestment(?string $investorName = null): void
    {
        abort_unless($this->canManage && $this->profile, 403);

        $this->inv          = $this->blank();
        $this->inv['investor_name'] = (string) $investorName;
        $this->invProof     = null;
        $this->invEditingId = null;
        $this->invOpen      = true;
        $this->reviewingId  = null;
        $this->resetValidation();
    }

    public function editInvestment(int $id): void
    {
        abort_unless($this->canManage, 403);

        $i = $this->findInvestment($id);
        $this->inv = [
            'investor_name' => $i->investor_name, 'investor_type' => $i->investor_type ?? '', 'investor_country' => $i->investor_country ?? '',
            'amount' => (string) round((float) $i->amount), 'invested_on' => $i->invested_on?->format('Y-m-d') ?? '',
            'instrument' => $i->instrument ?? '', 'round_label' => $i->round_label ?? '', 'notes' => $i->notes ?? '',
        ];
        $this->invProof     = null;
        $this->invEditingId = $i->id;
        $this->invOpen      = true;
        $this->reviewingId  = null;
        $this->resetValidation();
    }

    public function cancelInvestment(): void
    {
        $this->invOpen      = false;
        $this->invEditingId = null;
        $this->invProof     = null;
        $this->resetValidation();
    }

    public function saveInvestment(): void
    {
        abort_unless($this->canManage && $this->profile, 403);

        $this->validate([
            'inv.investor_name'    => ['required', 'string', 'max:255'],
            'inv.investor_type'    => ['nullable', Rule::in(EnterpriseProfile::INVESTOR_TYPES)],
            'inv.investor_country' => ['nullable', 'string', 'max:80'],
            'inv.amount'           => ['required', 'numeric', 'gt:0', 'max:9999999999999'],
            'inv.invested_on'      => ['required', 'date', 'before_or_equal:today'],
            'inv.instrument'       => ['nullable', Rule::in(array_keys(EnterpriseInvestment::INSTRUMENTS))],
            'inv.round_label'      => ['nullable', 'string', 'max:60'],
            'inv.notes'            => ['nullable', 'string', 'max:1000'],
            'invProof'             => ['nullable', 'file', 'mimes:'.EnterpriseInvestment::PROOF_MIMES, 'max:'.EnterpriseInvestment::MAX_KB],
        ], [
            'invProof.max'   => 'The proof must be '.(EnterpriseInvestment::MAX_KB / 1024).' MB or smaller.',
            'invProof.mimes' => 'Upload a PDF, image, Word or Excel file.',
        ], [
            'inv.investor_name' => 'investor name', 'inv.amount' => 'amount', 'inv.invested_on' => 'date of investment',
            'inv.investor_type' => 'investor type', 'inv.instrument' => 'instrument', 'inv.round_label' => 'round', 'invProof' => 'proof',
        ]);

        $i = $this->invEditingId
            ? $this->findInvestment($this->invEditingId)
            : new EnterpriseInvestment(['enterprise_profile_id' => $this->profile->id, 'source' => 'reviewer', 'recorded_by' => auth()->id()]);

        $i->fill(collect($this->inv)->map(fn ($v) => $v === '' ? null : $v)->all());

        if ($this->invProof) {
            if ($i->proof_path) {
                Storage::disk(EnterpriseInvestment::DISK)->delete($i->proof_path);
            }
            $i->fill([
                'proof_path' => $this->invProof->store("enterprise-investments/{$this->profile->id}", EnterpriseInvestment::DISK),
                'proof_name' => $this->invProof->getClientOriginalName(),
                'proof_size' => $this->invProof->getSize(),
            ]);
        }
        $i->save();

        $this->cancelInvestment();
        $this->refreshData();
        $this->dispatch('notify', type: 'success', message: 'Investment saved.');
    }

    public function deleteInvestment(int $id): void
    {
        abort_unless($this->canManage, 403);

        $this->findInvestment($id)->delete();
        $this->refreshData();
        $this->dispatch('notify', type: 'success', message: 'Investment removed.');
    }

    /* ---------------- Verify / reject ---------------- */

    public function startReview(int $id, string $status): void
    {
        abort_unless($this->canManage && in_array($status, [EnterpriseInvestment::STATUS_VERIFIED, EnterpriseInvestment::STATUS_REJECTED], true), 403);

        $i = $this->findInvestment($id);
        $this->reviewingId  = $i->id;
        $this->reviewStatus = $status;
        $this->reviewNote   = (string) $i->review_note;
        $this->invOpen      = false;
        $this->resetValidation();
    }

    public function cancelReview(): void
    {
        $this->reviewingId = null;
        $this->reviewNote  = '';
        $this->resetValidation();
    }

    public function confirmReview(): void
    {
        abort_unless($this->canManage && $this->reviewingId, 403);

        $this->validate(
            ['reviewNote' => [$this->reviewStatus === EnterpriseInvestment::STATUS_REJECTED ? 'required' : 'nullable', 'string', 'max:1000']],
            ['reviewNote.required' => 'Say why the investment is rejected.'],
        );

        $this->findInvestment($this->reviewingId)->update([
            'status'      => $this->reviewStatus,
            'review_note' => $this->reviewNote ?: null,
            'reviewed_by' => auth()->id(),
            'reviewed_at' => now(),
        ]);

        $message = $this->reviewStatus === EnterpriseInvestment::STATUS_VERIFIED ? 'Investment verified.' : 'Investment rejected.';
        $this->cancelReview();
        $this->refreshData();
        $this->dispatch('notify', type: 'success', message: $message);
    }

    /** Back to "not yet verified" (e.g. verified by mistake) */
    public function resetReview(int $id): void
    {
        abort_unless($this->canManage, 403);

        $this->findInvestment($id)->update(['status' => EnterpriseInvestment::STATUS_DECLARED, 'review_note' => null, 'reviewed_by' => null, 'reviewed_at' => null]);
        $this->refreshData();
    }

    /* ---------------- Helpers ---------------- */

    protected function findInvestment(int $id): EnterpriseInvestment
    {
        abort_unless($this->profile, 404);

        return EnterpriseInvestment::where('enterprise_profile_id', $this->profile->id)->findOrFail($id);
    }

    protected function refreshData(): void
    {
        unset($this->investments, $this->groups, $this->facility);
    }
};
?>

<div>
    @php
        $p         = $this->profile;
        $I         = \App\Models\EnterpriseInvestment::class;
        $P         = \App\Models\EnterpriseProfile::class;
        $canManage = $this->canManage;
        $all       = $this->investments;
        $groups    = $this->groups;
        $facility  = $this->facility;

        $rate = max((float) config('cif.usd_to_lsl', 18.00), 0.0001);
        $lsl  = fn ($m) => $m === null || $m === '' ? '—' : 'M '.number_format((float) $m);
        $usd  = fn ($m) => $m === null || $m === '' ? '' : 'US$ '.number_format((float) $m / $rate);

        $counted   = $all->where('status', '!=', $I::STATUS_REJECTED);
        $total     = (float) $counted->sum('amount');
        $verified  = (float) $all->where('status', $I::STATUS_VERIFIED)->sum('amount');
        $pending   = $all->where('status', $I::STATUS_DECLARED)->count();
        $repeat    = $groups->where('count', '>', 1)->count();
        $shown     = $investor === '' ? $all : $all->filter(fn ($i) => $I::investorKey($i->investor_name) === $investor);
        $roundRows = $p ? $p->investorRows() : [];
    @endphp

    {{-- ============================ INTRO ============================ --}}
    <div class="card card-modern mb-4">
        <div class="card-body d-flex flex-wrap align-items-center gap-3">
            <span class="stat-icon tone-primary"><i class="bi bi-bank"></i></span>
            <div class="me-auto" style="max-width: 640px">
                <h5 class="mb-1 text-navy fw-bold">Investment history</h5>
                <div class="small text-muted">Every investment made into {{ $p?->short_name ?: ($p?->registered_name ?: $this->eoi->business_name) }}, one row per investment. Use it at Gate 1 to judge the investor's track record — how often and how much each investor has put in — before continuing with or rejecting the application.</div>
            </div>
            <button type="button" class="btn btn-sm btn-outline-secondary" wire:click="$parent.showTab('g1')"><i class="bi bi-arrow-left me-1"></i> Gate 1 · Eligibility</button>
            @if ($canManage && $p)
                <button type="button" class="btn btn-sm btn-brand" wire:click="newInvestment" @disabled($invOpen)><i class="bi bi-plus-lg me-1"></i> Record investment</button>
            @endif
        </div>
    </div>

    @if (! $p)
        <div class="callout callout-warning small"><i class="bi bi-exclamation-triangle me-1"></i> This enterprise has no profile yet, so there is no investment history to show.</div>
    @else

    {{-- ============================ KPIs ============================ --}}
    <div class="row g-3 mb-4">
        @foreach ([
            ['Investments', $counted->count(), $pending ? $pending.' not yet verified' : 'all reviewed', 'bi-receipt'],
            ['Total invested', $lsl($total), $usd($total), 'bi-cash-stack'],
            ['Investors', $groups->where('count', '>', 0)->count(), $repeat ? $repeat.' invested more than once' : 'no repeat investors', 'bi-people'],
            ['Verified', $lsl($verified), $total > 0 ? round($verified / $total * 100).'% of the total' : '—', 'bi-patch-check'],
        ] as [$kLabel, $kValue, $kSub, $kIcon])
            <div class="col-6 col-lg-3">
                <div class="card card-modern h-100"><div class="card-body">
                    <div class="small text-muted fw-semibold"><i class="bi {{ $kIcon }} me-1"></i> {{ $kLabel }}</div>
                    <div class="fs-5 fw-bold text-navy">{{ $kValue }}</div>
                    <div class="small text-muted">{{ $kSub }}</div>
                </div></div>
            </div>
        @endforeach
    </div>

    {{-- ============================ RECORD / EDIT FORM ============================ --}}
    @if ($invOpen && $canManage)
        <div class="card card-modern mb-4">
            <div class="card-body">
                <div class="card-title-sm"><i class="bi bi-pencil-square"></i> {{ $invEditingId ? 'Edit investment' : 'Record an investment' }}</div>
                <div class="row g-2">
                    <div class="col-md-6"><label class="form-label small fw-semibold">Investor name <span class="req">*</span></label>
                        <input type="text" class="form-control form-control-sm @error('inv.investor_name') is-invalid @enderror" wire:model="inv.investor_name" list="known-investors">
                        <datalist id="known-investors">@foreach ($groups as $g)<option value="{{ $g['name'] }}">@endforeach @foreach ($roundRows as $r)<option value="{{ $r['name'] }}">@endforeach</datalist>
                        @error('inv.investor_name') <div class="invalid-feedback">{{ $message }}</div> @enderror</div>
                    <div class="col-md-3"><label class="form-label small fw-semibold">Investor type</label>
                        <select class="form-select form-select-sm" wire:model="inv.investor_type"><option value="">Select…</option>@foreach ($P::INVESTOR_TYPES as $o)<option>{{ $o }}</option>@endforeach</select></div>
                    <div class="col-md-3"><label class="form-label small fw-semibold">Country</label>
                        <input type="text" class="form-control form-control-sm" wire:model="inv.investor_country"></div>
                    <div class="col-md-4"><label class="form-label small fw-semibold">Amount (M) <span class="req">*</span></label>
                        <input type="number" min="0" step="1000" class="form-control form-control-sm @error('inv.amount') is-invalid @enderror" wire:model.blur="inv.amount">
                        <div class="form-text">{{ $usd($inv['amount'] ?? null) }}</div>
                        @error('inv.amount') <div class="invalid-feedback">{{ $message }}</div> @enderror</div>
                    <div class="col-md-4"><label class="form-label small fw-semibold">Date of investment <span class="req">*</span></label>
                        <input type="date" max="{{ now()->toDateString() }}" class="form-control form-control-sm @error('inv.invested_on') is-invalid @enderror" wire:model="inv.invested_on">
                        @error('inv.invested_on') <div class="invalid-feedback">{{ $message }}</div> @enderror</div>
                    <div class="col-md-4"><label class="form-label small fw-semibold">Instrument</label>
                        <select class="form-select form-select-sm" wire:model="inv.instrument"><option value="">Select…</option>@foreach ($I::INSTRUMENTS as $k => $o)<option value="{{ $k }}">{{ $o }}</option>@endforeach</select></div>
                    <div class="col-md-4"><label class="form-label small fw-semibold">Round</label>
                        <input type="text" class="form-control form-control-sm" wire:model="inv.round_label" placeholder="e.g. Seed, Series A"></div>
                    <div class="col-md-8"><label class="form-label small fw-semibold">Proof of investment</label>
                        <input type="file" class="form-control form-control-sm @error('invProof') is-invalid @enderror" wire:model="invProof">
                        <div class="form-text">Bank statement, share certificate or signed agreement.</div>
                        @error('invProof') <div class="invalid-feedback">{{ $message }}</div> @enderror</div>
                    <div class="col-12"><label class="form-label small fw-semibold">Notes</label>
                        <input type="text" class="form-control form-control-sm" wire:model="inv.notes"></div>
                </div>
                <div class="d-flex gap-2 mt-3">
                    <button type="button" class="btn btn-link btn-sm text-secondary text-decoration-none px-0" wire:click="cancelInvestment">Cancel</button>
                    <button type="button" class="btn btn-brand btn-sm ms-auto" wire:click="saveInvestment" wire:loading.attr="disabled" wire:target="saveInvestment,invProof"><i class="bi bi-check-lg me-1"></i> Save investment</button>
                </div>
            </div>
        </div>
    @endif

    {{-- ============================ BY INVESTOR ============================ --}}
    <div class="card card-modern mb-4">
        <div class="card-body">
            <div class="card-title-sm"><i class="bi bi-people"></i> By investor</div>
            @if ($groups->isEmpty())
                <p class="small text-muted mb-0">No investments recorded yet.</p>
            @else
                <div class="table-responsive">
                    <table class="table table-modern table-sm align-middle mb-0">
                        <thead><tr><th>Investor</th><th class="text-center">Times invested</th><th class="text-end">Total</th><th class="text-end">Verified</th><th>First – last</th><th>Across the facility</th><th></th></tr></thead>
                        <tbody>
                            @foreach ($groups as $g)
                                @php $fr = $facility[$g['key']] ?? null; @endphp
                                <tr wire:key="grp-{{ md5($g['key']) }}" class="{{ $investor === $g['key'] ? 'table-active' : '' }}">
                                    <td><div class="fw-semibold text-navy small">{{ $g['name'] }}</div>
                                        <div class="small text-muted">{{ collect([$g['type'], $g['country']])->filter()->implode(' · ') }}</div></td>
                                    <td class="text-center">
                                        <span class="fw-bold text-navy">{{ $g['count'] }}</span>
                                        @if ($g['count'] > 1) <span class="pill pill-ok ms-1"><i class="bi bi-arrow-repeat"></i> Repeat</span> @endif
                                    </td>
                                    <td class="text-end text-nowrap">{{ $lsl($g['total']) }}<div class="small text-muted">{{ $usd($g['total']) }}</div></td>
                                    <td class="text-end text-nowrap {{ $g['verified'] < $g['total'] ? 'text-warning' : '' }}">{{ $lsl($g['verified']) }}</td>
                                    <td class="small text-nowrap">{{ $g['first']?->format('M Y') }} @if ($g['count'] > 1) – {{ $g['last']?->format('M Y') }} @endif</td>
                                    <td class="small text-nowrap">@if ($fr && $fr['investments'])
                                            <a href="#track-{{ md5($g['key']) }}" class="text-decoration-none fw-semibold">{{ $fr['investments'] }} in {{ $fr['enterprises'] }} {{ \Illuminate\Support\Str::plural('enterprise', $fr['enterprises']) }}</a>
                                            <div class="text-muted">{{ $lsl($fr['total']) }}</div>
                                        @else <span class="text-muted">—</span> @endif</td>
                                    <td class="text-end"><button type="button" class="btn btn-sm btn-outline-secondary" wire:click="showInvestor('{{ $g['key'] }}')">{{ $investor === $g['key'] ? 'Show all' : 'Show' }}</button></td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
                <div class="small text-muted mt-2"><i class="bi bi-info-circle me-1"></i> Rejected investments are not counted. Investors are matched by name, ignoring "(Pty) Ltd" and punctuation.</div>
            @endif
        </div>
    </div>

    {{-- ============================ CURRENT ROUND ============================ --}}
    @if ($roundRows)
        <div class="card card-modern mb-4">
            <div class="card-body">
                <div class="card-title-sm"><i class="bi bi-briefcase"></i> Investors in the current round <span class="small text-muted fw-normal">· from the enterprise profile</span></div>
                <table class="table table-modern table-sm align-middle mb-0">
                    <thead><tr><th>Investor</th><th class="text-end">Committed now</th><th>Invested here before</th><th>Across the facility</th></tr></thead>
                    <tbody>
                        @foreach ($roundRows as $r)
                            @php
                                $k     = $I::investorKey($r['name']);
                                $prev  = $groups->firstWhere('key', $k);
                                $fr    = $facility[$k] ?? null;
                            @endphp
                            <tr>
                                <td><div class="fw-semibold text-navy small">{{ $r['name'] }}</div><div class="small text-muted">{{ $r['country'] ?? '' }}</div></td>
                                <td class="text-end text-nowrap">{{ $lsl($r['ticket'] ?? null) }}<div class="small text-muted">{{ $usd($r['ticket'] ?? null) }}</div></td>
                                <td class="small">
                                    @if ($prev && $prev['count'] > 0)
                                        <span class="pill pill-ok"><i class="bi bi-arrow-repeat"></i> {{ $prev['count'] }} {{ \Illuminate\Support\Str::plural('time', $prev['count']) }}</span> · {{ $lsl($prev['total']) }}
                                    @else
                                        <span class="pill pill-muted">New investor</span>
                                    @endif
                                </td>
                                <td class="small">@if ($fr && $fr['investments'])
                                        <a href="#track-{{ md5($k) }}" class="text-decoration-none fw-semibold">{{ $fr['investments'] }} {{ \Illuminate\Support\Str::plural('investment', $fr['investments']) }} in {{ $fr['enterprises'] }} {{ \Illuminate\Support\Str::plural('enterprise', $fr['enterprises']) }}</a>
                                    @else <span class="pill pill-muted">No record in the facility</span> @endif</td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
        </div>
    @endif

    {{-- ============================ TRACK RECORD ACROSS THE FACILITY ============================ --}}
    @if ($facility)
        <div class="card card-modern mb-4">
            <div class="card-body">
                <div class="card-title-sm"><i class="bi bi-diagram-3"></i> Investor track record across the facility</div>
                <p class="small text-muted mb-3">How many times each investor has invested in enterprises on the facility, and how much. Rejected investments are not counted; current-round commitments are shown separately.</p>
                @foreach ($facility as $key => $fr)
                    @php $fName = $groups->firstWhere('key', $key)['name'] ?? (collect($roundRows)->first(fn ($r) => $I::investorKey($r['name']) === $key)['name'] ?? $key); @endphp
                    <details class="border rounded-3 mb-2" id="track-{{ md5($key) }}" @if ($loop->first) open @endif>
                        <summary class="d-flex flex-wrap align-items-center gap-2 p-2" style="cursor: pointer">
                            <span class="fw-semibold text-navy small me-auto">{{ $fName }}</span>
                            <span class="pill {{ $fr['investments'] > 1 ? 'pill-ok' : 'pill-muted' }}"><i class="bi bi-receipt"></i> {{ $fr['investments'] }} {{ \Illuminate\Support\Str::plural('investment', $fr['investments']) }}</span>
                            <span class="pill pill-muted"><i class="bi bi-buildings"></i> {{ $fr['enterprises'] }} {{ \Illuminate\Support\Str::plural('enterprise', $fr['enterprises']) }}</span>
                            <span class="small fw-semibold text-navy">{{ $lsl($fr['total']) }}</span>
                            <span class="small text-muted">{{ $lsl($fr['verified']) }} verified</span>
                        </summary>
                        <div class="px-2 pb-2">
                            <table class="table table-modern table-sm align-middle mb-0">
                                <thead><tr><th>Enterprise</th><th class="text-center">Investments</th><th class="text-end">Invested</th><th class="text-end">Verified</th><th>First – last</th><th class="text-end">Current round</th></tr></thead>
                                <tbody>
                                    @foreach ($fr['per'] as $row)
                                        <tr class="{{ $row['current'] ? 'table-active' : '' }}">
                                            <td class="small fw-semibold text-navy">{{ $row['name'] }} @if ($row['current']) <span class="pill pill-muted ms-1">This application</span> @endif</td>
                                            <td class="text-center">{{ $row['count'] ?: '—' }}</td>
                                            <td class="text-end text-nowrap">{{ $row['count'] ? $lsl($row['total']) : '—' }}</td>
                                            <td class="text-end text-nowrap">{{ $row['count'] ? $lsl($row['verified']) : '—' }}</td>
                                            <td class="small text-nowrap">{{ $row['first']?->format('M Y') ?? '—' }} @if ($row['count'] > 1) – {{ $row['last']?->format('M Y') }} @endif</td>
                                            <td class="text-end text-nowrap small">{{ $row['committed'] !== null ? $lsl($row['committed']) : '—' }}</td>
                                        </tr>
                                    @endforeach
                                </tbody>
                            </table>
                        </div>
                    </details>
                @endforeach
            </div>
        </div>
    @endif

    {{-- ============================ ALL INVESTMENTS ============================ --}}
    <div class="card card-modern">
        <div class="card-body">
            <div class="d-flex flex-wrap align-items-center gap-2 mb-2">
                <div class="card-title-sm mb-0 me-auto"><i class="bi bi-list-ul"></i> {{ $investor === '' ? 'All investments' : 'Investments by '.($groups->firstWhere('key', $investor)['name'] ?? 'investor') }}</div>
                @if ($investor !== '') <button type="button" class="btn btn-sm btn-link text-decoration-none" wire:click="$set('investor', '')">Show all</button> @endif
            </div>

            @if ($shown->isEmpty())
                <p class="small text-muted mb-0">No investments to show.</p>
            @else
                <div class="table-responsive">
                    <table class="table table-modern table-sm align-middle mb-0">
                        <thead><tr><th>Date</th><th>Investor</th><th class="text-end">Amount</th><th>Instrument / round</th><th>Status</th>@if ($canManage)<th class="text-end">Actions</th>@endif</tr></thead>
                        <tbody>
                            @foreach ($shown as $i)
                                @php [$sLabel, $sPill, $sIcon] = $I::STATUSES[$i->status] ?? $I::STATUSES['declared']; @endphp
                                <tr wire:key="inv-{{ $i->id }}">
                                    <td class="small text-nowrap">{{ $i->invested_on?->format('d M Y') }}</td>
                                    <td><div class="fw-semibold text-navy small">{{ $i->investor_name }}</div>
                                        <div class="small text-muted">{{ $I::SOURCES[$i->source] ?? '' }}@if ($i->recorder && $i->source === 'reviewer') ({{ $i->recorder->name }})@endif</div>
                                        @if ($i->notes) <div class="small text-muted fst-italic">{{ $i->notes }}</div> @endif</td>
                                    <td class="text-end text-nowrap">{{ $lsl($i->amount) }}<div class="small text-muted">{{ $usd($i->amount) }}</div></td>
                                    <td class="small">{{ collect([$I::INSTRUMENTS[$i->instrument ?? ''] ?? null, $i->round_label])->filter()->implode(' · ') ?: '—' }}</td>
                                    <td>
                                        <span class="pill {{ $sPill }}"><i class="bi {{ $sIcon }}"></i> {{ $sLabel }}</span>
                                        @if ($i->reviewer) <div class="small text-muted mt-1">{{ $i->reviewer->name }}, {{ $i->reviewed_at?->format('d M Y') }}</div> @endif
                                        @if ($i->review_note) <div class="small {{ $i->status === 'rejected' ? 'text-danger' : 'text-muted' }}">{{ $i->review_note }}</div> @endif
                                        @if ($i->proof_path)
                                            <a href="{{ route('investments.proof', $i) }}" class="small d-block text-decoration-none mt-1"><i class="bi bi-paperclip"></i> {{ \Illuminate\Support\Str::limit($i->proof_name, 28) }} · {{ $i->proof_size_label }}</a>
                                        @else
                                            <div class="small text-muted mt-1"><i class="bi bi-paperclip"></i> No proof</div>
                                        @endif
                                    </td>
                                    @if ($canManage)
                                        <td class="text-end text-nowrap">
                                            @if ($i->status === 'declared')
                                                <button type="button" class="btn btn-sm btn-outline-success" wire:click="startReview({{ $i->id }}, 'verified')" title="Verify"><i class="bi bi-check-lg"></i></button>
                                                <button type="button" class="btn btn-sm btn-outline-danger" wire:click="startReview({{ $i->id }}, 'rejected')" title="Reject"><i class="bi bi-x-lg"></i></button>
                                            @else
                                                <button type="button" class="btn btn-sm btn-outline-secondary" wire:click="resetReview({{ $i->id }})" wire:confirm="Set this investment back to 'not yet verified'?" title="Undo review"><i class="bi bi-arrow-counterclockwise"></i></button>
                                            @endif
                                            <button type="button" class="btn btn-icon" title="Edit" wire:click="editInvestment({{ $i->id }})"><i class="bi bi-pencil"></i></button>
                                            <button type="button" class="btn btn-icon btn-icon-danger" title="Delete" wire:click="deleteInvestment({{ $i->id }})" wire:confirm="Delete this investment record?"><i class="bi bi-trash"></i></button>
                                        </td>
                                    @endif
                                </tr>
                                @if ($canManage && $reviewingId === $i->id)
                                    <tr wire:key="inv-review-{{ $i->id }}">
                                        <td colspan="6" class="bg-light">
                                            <div class="d-flex flex-wrap align-items-start gap-2">
                                                <div class="flex-grow-1">
                                                    <input type="text" class="form-control form-control-sm @error('reviewNote') is-invalid @enderror" wire:model="reviewNote"
                                                           placeholder="{{ $reviewStatus === 'rejected' ? 'Why is it rejected? (required — the enterprise sees this)' : 'Note (optional), e.g. checked against bank statement' }}">
                                                    @error('reviewNote') <div class="invalid-feedback">{{ $message }}</div> @enderror
                                                </div>
                                                <button type="button" class="btn btn-sm btn-link text-secondary text-decoration-none" wire:click="cancelReview">Cancel</button>
                                                <button type="button" class="btn btn-sm {{ $reviewStatus === 'rejected' ? 'btn-danger' : 'btn-success' }}" wire:click="confirmReview">
                                                    {{ $reviewStatus === 'rejected' ? 'Reject investment' : 'Mark as verified' }}
                                                </button>
                                            </div>
                                        </td>
                                    </tr>
                                @endif
                            @endforeach
                        </tbody>
                    </table>
                </div>
            @endif
        </div>
    </div>
    @endif
</div>