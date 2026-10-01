<x-app-layout>

@php
    /*
     | TEMPLATE: sample submissions so the list renders.
     | Later, pass real records as $eois and delete the sample block.
     */
    $eois = collect($eois ?? [
        (object) ['id' => 1, 'reference' => 'EOI-2026-0001', 'business_name' => 'Maluti Fresh Foods', 'district' => 'Maseru',
                  'representative_name' => 'Thabo Mokoena', 'phone' => '+266 5800 1111', 'email' => 'thabo@malutifresh.co.ls',
                  'legal_status' => 'registered_operating', 'year_commenced' => 2019, 'annual_turnover' => '2m_5m', 'full_time_employees' => 14,
                  'business_description' => 'We process and package dried fruit for retailers across Lesotho and South Africa.',
                  'investment_required_usd' => 250000, 'investment_types' => ['equity'], 'use_of_funds' => ['equipment', 'working_capital'],
                  'use_of_funds_description' => 'New drying line and working capital for export orders.',
                  'investor_status' => 'term_sheet', 'investor_amount_usd' => 150000, 'investor_name' => 'Basotho Capital Partners',
                  'investor_evidence' => ['term_sheet'], 'support_need' => 'ci_facility', 'status' => 'in_review', 'submitted_at' => now()->subDays(12)],
        (object) ['id' => 2, 'reference' => 'EOI-2026-0002', 'business_name' => 'Senqu Solar Solutions', 'district' => 'Leribe',
                  'representative_name' => 'Lineo Ramakatane', 'phone' => '+266 6200 2222', 'email' => 'lineo@senqusolar.co.ls',
                  'legal_status' => 'registered_operating', 'year_commenced' => 2021, 'annual_turnover' => '500k_2m', 'full_time_employees' => 8,
                  'business_description' => 'Solar home systems and installation for rural households and small businesses.',
                  'investment_required_usd' => 120000, 'investment_types' => ['debt'], 'use_of_funds' => ['market_expansion'],
                  'use_of_funds_description' => 'Expand distribution into three new districts.',
                  'investor_status' => 'interest', 'investor_amount_usd' => null, 'investor_name' => null,
                  'investor_evidence' => [], 'support_need' => 'progress_deal', 'status' => 'returned', 'submitted_at' => now()->subDays(6)],
        (object) ['id' => 3, 'reference' => 'EOI-2026-0003', 'business_name' => 'Highlands Wool Co.', 'district' => 'Mokhotlong',
                  'representative_name' => 'Teboho Lebona', 'phone' => '+266 5900 3333', 'email' => 'info@highlandswool.co.ls',
                  'legal_status' => 'registered_not_operating', 'year_commenced' => 2025, 'annual_turnover' => 'below_500k', 'full_time_employees' => 3,
                  'business_description' => 'Washing and spinning mohair and wool for local weavers.',
                  'investment_required_usd' => 80000, 'investment_types' => ['equity'], 'use_of_funds' => ['equipment'],
                  'use_of_funds_description' => 'Purchase a small spinning plant.',
                  'investor_status' => 'none', 'investor_amount_usd' => null, 'investor_name' => null,
                  'investor_evidence' => [], 'support_need' => 'find_investor', 'status' => 'draft', 'submitted_at' => null],
        (object) ['id' => 4, 'reference' => 'EOI-2026-0004', 'business_name' => 'Katse Digital Labs', 'district' => 'Thaba-Tseka',
                  'representative_name' => 'Palesa Mohapi', 'phone' => '+266 5700 4444', 'email' => 'hello@katsedigital.co.ls',
                  'legal_status' => 'registered_operating', 'year_commenced' => 2018, 'annual_turnover' => '5m_10m', 'full_time_employees' => 32,
                  'business_description' => 'Software and payment integrations for SMEs and public institutions.',
                  'investment_required_usd' => 400000, 'investment_types' => ['equity', 'quasi_equity'], 'use_of_funds' => ['technology', 'product_development'],
                  'use_of_funds_description' => 'Build a regional payments platform.',
                  'investor_status' => 'committed', 'investor_amount_usd' => 300000, 'investor_name' => 'Southern Africa Growth Fund',
                  'investor_evidence' => ['investment_agreement'], 'support_need' => 'ci_facility', 'status' => 'invited', 'submitted_at' => now()->subDays(30)],
    ]);

    $statuses = [
        'draft'          => ['Draft',          'status-draft',     'bi-pencil'],
        'submitted'      => ['Submitted',      'status-submitted', 'bi-send'],
        'returned'       => ['Returned',       'status-referred',  'bi-arrow-return-left'],
        'in_review'      => ['In review',      'status-in-review', 'bi-hourglass-split'],
        'invited'        => ['Invited to CI',  'status-approved',  'bi-patch-check'],
        'not_progressed' => ['Not progressed', 'status-rejected',  'bi-x-circle'],
        'withdrawn'      => ['Withdrawn',      'status-draft',     'bi-slash-circle'],
    ];

    $investorLabels = [
        'none' => 'No investor', 'discussions' => 'In discussions', 'interest' => 'Interest indicated',
        'loi' => 'Letter of Intent', 'term_sheet' => 'Term Sheet', 'committed' => 'Committed', 'other' => 'Other',
    ];

    // Who can edit / withdraw — adjust to your workflow rules
    $editable    = ['draft', 'returned'];
    $withdrawable = ['draft', 'submitted', 'returned', 'in_review'];

    $count = fn (...$s) => $eois->whereIn('status', $s)->count();
@endphp

<div class="eoi-list">

    {{-- ============================ HEADER ============================ --}}
    <div class="d-flex flex-wrap align-items-center gap-3 mb-4">
        <div class="me-auto">
            <h2 class="eoi-title mb-1">Expressions of Interest</h2>
            <p class="text-muted small mb-0">Track your submitted applications to the CAFI Co-Investment Facility.</p>
        </div>
        <button type="button" class="btn btn-eoi-apply" data-bs-toggle="offcanvas" data-bs-target="#eoiDrawer" data-eoi-new>
            <i class="bi bi-plus-lg me-1"></i> Apply for Co-Investment
        </button>
    </div>

    {{-- ============================ STATS ============================ --}}
    <div class="row g-3 mb-4">
        @foreach ([
            ['Total applications', $eois->count(),                         'bi-collection',      'primary'],
            ['Drafts',             $count('draft'),                         'bi-pencil-square',   'muted'],
            ['Under review',       $count('submitted', 'in_review'),        'bi-hourglass-split', 'warning'],
            ['Invited to CI',      $count('invited'),                       'bi-patch-check',     'accent'],
        ] as [$label, $value, $icon, $tone])
            <div class="col-6 col-xl-3">
                <div class="eoi-stat">
                    <span class="eoi-stat-icon tone-{{ $tone }}"><i class="bi {{ $icon }}"></i></span>
                    <div>
                        <div class="eoi-stat-value">{{ $value }}</div>
                        <div class="eoi-stat-label">{{ $label }}</div>
                    </div>
                </div>
            </div>
        @endforeach
    </div>

    {{-- ============================ TABLE ============================ --}}
    <div class="card">
        <div class="card-header bg-white py-3">
            <div class="row g-2 align-items-center">
                <div class="col-md">
                    <div class="eoi-search">
                        <i class="bi bi-search"></i>
                        <input type="search" class="form-control" id="eoiSearch" placeholder="Search by reference, business or district…">
                    </div>
                </div>
                <div class="col-md-auto">
                    <select class="form-select" id="eoiStatusFilter">
                        <option value="">All statuses</option>
                        @foreach ($statuses as $key => [$label])
                            <option value="{{ $key }}">{{ $label }}</option>
                        @endforeach
                    </select>
                </div>
            </div>
        </div>

        @if ($eois->isEmpty())
            <div class="card-body text-center py-5">
                <div class="eoi-empty-icon mb-3"><i class="bi bi-file-earmark-plus"></i></div>
                <h5 class="fw-bold text-cif-navy mb-1">No applications yet</h5>
                <p class="text-muted small mb-3">Submit an Expression of Interest to be considered for the Co-Investment Facility.</p>
                <button type="button" class="btn btn-cif-primary btn-sm" data-bs-toggle="offcanvas" data-bs-target="#eoiDrawer" data-eoi-new>
                    <i class="bi bi-plus-lg me-1"></i> Apply now
                </button>
            </div>
        @else
            <div class="table-responsive">
                <table class="table table-hover align-middle mb-0 eoi-table" id="eoiTable">
                    <thead>
                        <tr>
                            <th class="ps-4">Reference</th>
                            <th>Business</th>
                            <th class="text-end">Investment (US$)</th>
                            <th>Investor</th>
                            <th>Submitted</th>
                            <th>Status</th>
                            <th class="text-end pe-4">Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach ($eois as $eoi)
                            @php
                                [$sLabel, $sClass, $sIcon] = $statuses[$eoi->status] ?? [ucfirst($eoi->status), 'status-draft', 'bi-circle'];
                                $submitted = $eoi->submitted_at ? \Illuminate\Support\Carbon::parse($eoi->submitted_at) : null;
                                $canEdit     = in_array($eoi->status, $editable, true);
                                $canWithdraw = in_array($eoi->status, $withdrawable, true);
                                // Full record for pre-filling the drawer on Edit
                                $record = collect((array) $eoi)->except(['status', 'submitted_at'])->all();
                            @endphp
                            <tr data-status="{{ $eoi->status }}"
                                data-search="{{ strtolower($eoi->reference.' '.$eoi->business_name.' '.$eoi->district) }}">
                                <td class="ps-4">
                                    <a href="{{ route('expression-of-interest.show', ['id' => $eoi->id]) }}" class="eoi-ref">{{ $eoi->reference }}</a>
                                </td>
                                <td>
                                    <div class="fw-semibold text-cif-navy">{{ $eoi->business_name }}</div>
                                    <div class="text-muted small"><i class="bi bi-geo-alt me-1"></i>{{ $eoi->district }}</div>
                                </td>
                                <td class="text-end fw-semibold">{{ number_format((float) $eoi->investment_required_usd) }}</td>
                                <td class="small">{{ $investorLabels[$eoi->investor_status] ?? '—' }}</td>
                                <td class="small text-muted">{{ $submitted?->format('d M Y') ?? '—' }}</td>
                                <td>
                                    <span class="badge {{ $sClass }}"><i class="bi {{ $sIcon }} me-1"></i>{{ $sLabel }}</span>
                                </td>
                                <td class="text-end pe-4">
                                    <div class="d-inline-flex gap-1">
                                        <a href="{{ route('expression-of-interest.show', ['id' => $eoi->id]) }}" class="btn btn-icon" title="View application">
                                            <i class="bi bi-eye"></i>
                                        </a>

                                        @if ($canEdit)
                                            <button type="button" class="btn btn-icon" title="Edit" data-eoi-edit='@json($record)'>
                                                <i class="bi bi-pencil"></i>
                                            </button>
                                        @endif

                                        <div class="dropdown">
                                            <button class="btn btn-icon" type="button" data-bs-toggle="dropdown" aria-expanded="false" title="More">
                                                <i class="bi bi-three-dots-vertical"></i>
                                            </button>
                                            <ul class="dropdown-menu dropdown-menu-end eoi-menu">
                                                <li><a class="dropdown-item" href="{{ route('expression-of-interest.show', ['id' => $eoi->id]) }}"><i class="bi bi-file-text"></i> View application</a></li>
                                                @if ($canEdit)
                                                    <li><a class="dropdown-item" href="#" data-eoi-edit='@json($record)'><i class="bi bi-pencil"></i> Edit</a></li>
                                                @endif
                                                <li><a class="dropdown-item" href="#" data-eoi-duplicate='@json($record)'><i class="bi bi-copy"></i> Use as new application</a></li>
                                                <li><a class="dropdown-item" href="#" onclick="window.print(); return false;"><i class="bi bi-printer"></i> Print</a></li>
                                                <li><a class="dropdown-item" href="#"><i class="bi bi-download"></i> Download PDF</a></li>
                                                @if ($canWithdraw)
                                                    <li><hr class="dropdown-divider"></li>
                                                    <li>
                                                        <form method="POST" action="#" data-confirm="Withdraw {{ $eoi->reference }}? This cannot be undone.">
                                                            @csrf
                                                            @method('DELETE')
                                                            <button type="submit" class="dropdown-item text-danger"><i class="bi bi-x-circle"></i> Withdraw</button>
                                                        </form>
                                                    </li>
                                                @endif
                                            </ul>
                                        </div>
                                    </div>
                                </td>
                            </tr>
                        @endforeach
                        <tr class="eoi-no-results" hidden>
                            <td colspan="7" class="text-center text-muted py-4">No applications match your search.</td>
                        </tr>
                    </tbody>
                </table>
            </div>
            <div class="card-footer bg-white small text-muted d-flex justify-content-between">
                <span><span id="eoiVisibleCount">{{ $eois->count() }}</span> of {{ $eois->count() }} applications</span>
                <span><i class="bi bi-info-circle me-1"></i>Only drafts and returned applications can be edited.</span>
            </div>
        @endif
    </div>
</div>

<style>
.eoi-list {
    --cif-primary: #29317D; --cif-primary-dark: #1f2663;
    --cif-accent:  #089E49; --cif-accent-dark:  #07863e;
    --cif-navy:    #0d1a3a; --cif-soft: #f0f2fa; --cif-border: #e2e8f0;
}
.text-cif-navy { color: var(--cif-navy) !important; }
.eoi-title { font-weight: 800; color: var(--cif-navy); font-size: 1.5rem; }
.btn-cif-primary { background: var(--cif-primary); border-color: var(--cif-primary); color: #fff; font-weight: 600; }
.btn-cif-primary:hover { background: var(--cif-primary-dark); border-color: var(--cif-primary-dark); color: #fff; }
.btn-eoi-apply { background: var(--cif-accent); color: #fff; font-weight: 700; border: 0; border-radius: .6rem; padding: .65rem 1.2rem;
                 box-shadow: 0 8px 20px rgba(8,158,73,.25); }
.btn-eoi-apply:hover { background: var(--cif-accent-dark); color: #fff; }

/* Status badges */
.status-draft     { background: #e2e8f0; color: #475569; }
.status-submitted { background: rgba(41,49,125,.12); color: #29317D; }
.status-in-review { background: #fef3c7; color: #92400e; }
.status-referred  { background: #ffedd5; color: #9a3412; }
.status-approved  { background: rgba(8,158,73,.12); color: #07863e; }
.status-rejected  { background: #fee2e2; color: #b91c1c; }

/* Stats */
.eoi-stat { display: flex; align-items: center; gap: .9rem; background: #fff; border: 1px solid var(--cif-border); border-radius: .75rem; padding: 1rem 1.1rem; height: 100%; }
.eoi-stat-icon { width: 44px; height: 44px; border-radius: 12px; display: flex; align-items: center; justify-content: center; font-size: 1.2rem; flex-shrink: 0; }
.eoi-stat-value { font-size: 1.5rem; font-weight: 800; color: var(--cif-navy); line-height: 1; }
.eoi-stat-label { font-size: .75rem; color: #64748b; margin-top: .25rem; }
.tone-primary { background: rgba(41,49,125,.1); color: var(--cif-primary); }
.tone-accent  { background: rgba(8,158,73,.1);  color: var(--cif-accent); }
.tone-warning { background: #fef3c7; color: #b45309; }
.tone-muted   { background: #f1f5f9; color: #64748b; }

/* Table */
.eoi-list .card { border: 1px solid var(--cif-border); border-radius: .75rem; overflow: visible; }
.eoi-search { position: relative; }
.eoi-search i { position: absolute; left: .8rem; top: 50%; transform: translateY(-50%); color: #94a3b8; }
.eoi-search .form-control { padding-left: 2.2rem; }
.eoi-list .form-control:focus, .eoi-list .form-select:focus { border-color: var(--cif-primary); box-shadow: 0 0 0 .2rem rgba(41,49,125,.12); }
.eoi-table thead th { background: #f8fafc; font-size: .72rem; font-weight: 700; text-transform: uppercase; letter-spacing: .5px; color: #64748b; border-bottom: 1px solid var(--cif-border); }
.eoi-table td { font-size: .875rem; }
.eoi-ref { font-weight: 700; color: var(--cif-primary); text-decoration: none; }
.eoi-ref:hover { color: var(--cif-accent); text-decoration: underline; }
.btn-icon { width: 32px; height: 32px; padding: 0; border-radius: 8px; border: 1px solid var(--cif-border); background: #fff; color: #475569;
            display: inline-flex; align-items: center; justify-content: center; }
.btn-icon:hover, .btn-icon[aria-expanded="true"] { border-color: var(--cif-primary); color: var(--cif-primary); background: var(--cif-soft); }
.eoi-menu { border: 1px solid var(--cif-border); border-radius: .6rem; box-shadow: 0 12px 28px rgba(13,26,58,.12); padding: .35rem; font-size: .85rem; }
.eoi-menu .dropdown-item { display: flex; gap: .55rem; align-items: center; border-radius: .4rem; padding: .45rem .7rem; }
.eoi-menu .dropdown-item i { color: var(--cif-primary); }
.eoi-menu .dropdown-item:hover { background: rgba(8,158,73,.08); color: var(--cif-accent); }
.eoi-menu .dropdown-item.text-danger i { color: #dc3545; }
.eoi-menu .dropdown-item.text-danger:hover { background: #fee2e2; color: #b91c1c; }

.eoi-empty-icon { width: 64px; height: 64px; margin: 0 auto; border-radius: 16px; background: var(--cif-soft); color: var(--cif-primary);
                  display: flex; align-items: center; justify-content: center; font-size: 1.75rem; }

</style>

<script>
document.addEventListener('DOMContentLoaded', function () {
    /* ---------- Search + status filter ---------- */
    var search = document.getElementById('eoiSearch');
    var filter = document.getElementById('eoiStatusFilter');
    var table  = document.getElementById('eoiTable');

    function applyFilters() {
        if (!table) return;
        var q = (search.value || '').toLowerCase().trim();
        var s = filter.value;
        var shown = 0;
        table.querySelectorAll('tbody tr[data-status]').forEach(function (tr) {
            var ok = (!q || tr.dataset.search.indexOf(q) !== -1) && (!s || tr.dataset.status === s);
            tr.hidden = !ok;
            if (ok) shown++;
        });
        table.querySelector('.eoi-no-results').hidden = shown > 0;
        var c = document.getElementById('eoiVisibleCount');
        if (c) c.textContent = shown;
    }
    if (search) search.addEventListener('input', applyFilters);
    if (filter) filter.addEventListener('change', applyFilters);

    /* ---------- Edit / duplicate → open the apply drawer pre-filled ---------- */
    document.addEventListener('click', function (e) {
        var edit = e.target.closest('[data-eoi-edit]');
        var dup  = e.target.closest('[data-eoi-duplicate]');
        if (!edit && !dup) return;
        e.preventDefault();
        var data = JSON.parse((edit || dup).getAttribute(edit ? 'data-eoi-edit' : 'data-eoi-duplicate'));
        if (dup) { delete data.id; delete data.reference; }
        window.eoiDrawer.open(data, edit ? 'edit' : 'new');
    });


    /* ---------- Confirm before withdraw ---------- */
    document.querySelectorAll('form[data-confirm]').forEach(function (form) {
        form.addEventListener('submit', function (e) {
            if (form.dataset.confirmed) return;
            e.preventDefault();
            var msg = form.dataset.confirm;
            if (window.Swal) {
                Swal.fire({ title: 'Withdraw application?', text: msg, icon: 'warning', showCancelButton: true,
                            confirmButtonText: 'Yes, withdraw', confirmButtonColor: '#dc3545', cancelButtonColor: '#94a3b8' })
                    .then(function (r) { if (r.isConfirmed) { form.dataset.confirmed = 1; form.submit(); } });
            } else if (confirm(msg)) {
                form.dataset.confirmed = 1; form.submit();
            }
        });
    });
});
</script>

{{-- Apply / Edit drawer --}}
@include('expression-of-interest.partials.apply-drawer')

</x-app-layout>