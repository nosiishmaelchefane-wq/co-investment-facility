<?php

use App\Models\SentEmail;
use App\Models\User;
use Livewire\Attributes\Computed;
use Livewire\Attributes\On;
use Livewire\Component;
use Livewire\WithPagination;

/*
| Compose Email · "All e-mails" — a full-screen drawer with every e-mail sent from /compose-email.
| Opens on the "open-email-history" event (Recently sent → View all, header → Sent e-mails).
|   Left:  search, filters, list (paginated)
|   Right: the selected e-mail — message as written, recipients, attachments, "Use again" / "Continue"
| Drafts of other people are never shown.
*/
new class extends Component
{
    use WithPagination;

    public bool $open = false;

    public string $search = '';
    public string $status = '';      // '' | sent | partial | failed | draft
    public string $sender = '';      // user id
    public string $from = '';
    public string $to = '';

    public ?int $selectedId = null;

    #[On('open-email-history')]
    public function show(?int $id = null): void
    {
        $this->open = true;
        $this->selectedId = $id;
        unset($this->emails);
    }

    public function close(): void
    {
        $this->open = false;
    }

    public function updated($name): void
    {
        if (in_array($name, ['search', 'status', 'sender', 'from', 'to'], true)) {
            $this->resetPage();
        }
    }

    public function clearFilters(): void
    {
        $this->reset('search', 'status', 'sender', 'from', 'to');
        $this->resetPage();
    }

    public function select(int $id): void
    {
        $this->selectedId = $id;
    }

    /** Load into the composer and close the drawer */
    public function useEmail(int $id): void
    {
        $this->dispatch('load-email', id: $id);
        $this->open = false;
    }

    #[Computed]
    public function emails()
    {
        $me   = auth()->id();
        $term = trim($this->search);

        return SentEmail::query()
            ->with('sender:id,name')
            ->where(fn ($q) => $q->where('status', '!=', 'draft')->orWhere('sender_id', $me))   // only my own drafts
            ->when($this->status !== '', fn ($q) => $q->where('status', $this->status))
            ->when($this->sender !== '', fn ($q) => $q->where('sender_id', (int) $this->sender))
            ->when($this->from !== '', fn ($q) => $q->whereDate('created_at', '>=', $this->from))
            ->when($this->to !== '', fn ($q) => $q->whereDate('created_at', '<=', $this->to))
            ->when($term !== '', fn ($q) => $q->where(fn ($w) => $w
                ->where('subject', 'like', "%{$term}%")
                ->orWhere('recipient_list', 'like', "%{$term}%")
                ->orWhere('body', 'like', "%{$term}%")))
            ->orderByRaw('COALESCE(sent_at, updated_at) DESC')
            ->paginate(15);
    }

    #[Computed]
    public function selected(): ?SentEmail
    {
        if (! $this->selectedId) {
            return null;
        }
        $e = SentEmail::with(['sender:id,name,email', 'expressionOfInterest:id,reference,business_name'])->find($this->selectedId);

        return $e && ($e->status !== 'draft' || $e->sender_id === auth()->id()) ? $e : null;
    }

    /** People who have sent e-mails (for the filter) */
    #[Computed]
    public function senders()
    {
        return User::whereIn('id', SentEmail::select('sender_id')->distinct())->orderBy('name')->get(['id', 'name']);
    }

    #[Computed]
    public function totals(): array
    {
        $q = SentEmail::where('status', '!=', 'draft');

        return [
            'emails' => (clone $q)->count(),
            'people' => (int) (clone $q)->sum('sent_count'),
            'failed' => (int) (clone $q)->sum('failed_count'),
        ];
    }
};
?>

@php
    $S        = \App\Models\SentEmail::STATUSES;
    $groups   = \App\Support\EmailRecipients::GROUPS;
    $stages   = \App\Support\EmailRecipients::stages();
    $size     = fn ($b) => $b >= 1048576 ? round($b / 1048576, 1).' MB' : max(1, round($b / 1024)).' KB';
    $filtered = $search !== '' || $status !== '' || $sender !== '' || $from !== '' || $to !== '';
@endphp

<div>
    @if ($open)
        <div class="ce-drawer" role="dialog" aria-modal="true" aria-labelledby="ce-history-title" wire:keydown.escape.window="close">
            {{-- ============================ TOP BAR ============================ --}}
            <div class="ce-drawer-head d-flex flex-wrap align-items-center gap-3 px-4 py-3">
                <div class="me-auto">
                    <h4 class="fw-bold text-navy mb-0" id="ce-history-title"><i class="bi bi-envelope-paper me-2"></i>All e-mails</h4>
                    <div class="small text-muted">
                        {{ number_format($this->totals['emails']) }} sent · {{ number_format($this->totals['people']) }} deliveries
                        @if ($this->totals['failed']) · <span class="text-danger">{{ number_format($this->totals['failed']) }} failed</span> @endif
                    </div>
                </div>
                <button type="button" class="btn btn-outline-secondary btn-sm" wire:click="close"><i class="bi bi-x-lg me-1"></i> Close</button>
            </div>

            {{-- ============================ FILTERS ============================ --}}
            <div class="px-4 py-2 border-bottom bg-white">
                <div class="row g-2 align-items-end">
                    <div class="col-lg-4">
                        <div class="input-group input-group-sm">
                            <span class="input-group-text"><i class="bi bi-search"></i></span>
                            <input type="search" class="form-control" wire:model.live.debounce.400ms="search" placeholder="Search subject, message or recipient e-mail…">
                        </div>
                    </div>
                    <div class="col-6 col-lg-2">
                        <select class="form-select form-select-sm" wire:model.live="status">
                            <option value="">All statuses</option>
                            @foreach ($S as $k => [$l]) <option value="{{ $k }}">{{ $k === 'draft' ? 'My drafts' : $l }}</option> @endforeach
                        </select>
                    </div>
                    <div class="col-6 col-lg-2">
                        <select class="form-select form-select-sm" wire:model.live="sender">
                            <option value="">Anyone</option>
                            @foreach ($this->senders as $u) <option value="{{ $u->id }}">{{ $u->name }}</option> @endforeach
                        </select>
                    </div>
                    <div class="col-6 col-lg-auto"><input type="date" class="form-control form-control-sm" wire:model.live="from" title="From"></div>
                    <div class="col-6 col-lg-auto"><input type="date" class="form-control form-control-sm" wire:model.live="to" title="To"></div>
                    <div class="col-auto">
                        <span wire:loading wire:target="search,status,sender,from,to,gotoPage,nextPage,previousPage" class="spinner-border spinner-border-sm text-muted"></span>
                        @if ($filtered) <button type="button" class="btn btn-link btn-sm text-decoration-none" wire:click="clearFilters"><i class="bi bi-x-lg me-1"></i>Clear</button> @endif
                    </div>
                </div>
            </div>

            {{-- ============================ BODY ============================ --}}
            <div class="ce-drawer-body d-flex">
                {{-- List --}}
                <div class="ce-drawer-list border-end bg-white {{ $this->selected ? 'd-none d-lg-block' : '' }}">
                    @forelse ($this->emails as $e)
                        @php [$sl, $sc, $si] = $S[$e->status] ?? [$e->status, 'status-draft', 'bi-circle']; @endphp
                        <button type="button" wire:key="h-{{ $e->id }}" wire:click="select({{ $e->id }})"
                                class="ce-row w-100 text-start border-0 px-3 py-2 {{ $selectedId === $e->id ? 'is-active' : '' }}">
                            <div class="d-flex gap-2">
                                <span class="fw-semibold small text-navy text-truncate me-auto">{{ $e->subject ?: '(no subject)' }}</span>
                                <span class="small text-muted text-nowrap">{{ ($e->sent_at ?? $e->updated_at)->format('d M Y') }}</span>
                            </div>
                            <div class="d-flex gap-2 align-items-center mt-1">
                                <span class="badge {{ $sc }}"><i class="bi {{ $si }}"></i> {{ $sl }}</span>
                                <span class="small text-muted text-truncate">
                                    @if ($e->isDraft()) Not sent yet @else {{ $e->sent_count }} of {{ $e->recipient_count }} @endif · {{ $e->sender?->name }}
                                    @if (! empty($e->attachments)) · <i class="bi bi-paperclip"></i>{{ count($e->attachments) }} @endif
                                </span>
                            </div>
                        </button>
                    @empty
                        <div class="text-center text-muted small py-5">
                            <i class="bi bi-inbox fs-2 d-block mb-2"></i>
                            {{ $filtered ? 'No e-mails match these filters.' : 'No e-mails sent yet.' }}
                        </div>
                    @endforelse

                    @if ($this->emails->hasPages())
                        <div class="d-flex align-items-center justify-content-between px-3 py-2 border-top small">
                            <button type="button" class="btn btn-sm btn-outline-secondary" wire:click="previousPage" @disabled($this->emails->onFirstPage())><i class="bi bi-chevron-left"></i></button>
                            <span class="text-muted">Page {{ $this->emails->currentPage() }} of {{ $this->emails->lastPage() }} · {{ $this->emails->total() }} e-mails</span>
                            <button type="button" class="btn btn-sm btn-outline-secondary" wire:click="nextPage" @disabled(! $this->emails->hasMorePages())><i class="bi bi-chevron-right"></i></button>
                        </div>
                    @endif
                </div>

                {{-- Selected e-mail --}}
                <div class="ce-drawer-detail flex-grow-1 {{ $this->selected ? '' : 'd-none d-lg-flex' }}">
                    @if ($e = $this->selected)
                        @php
                            [$sl, $sc, $si] = $S[$e->status] ?? [$e->status, 'status-draft', 'bi-circle'];
                            $spec = array_merge(\App\Support\EmailRecipients::defaults(), (array) $e->recipients_spec);
                        @endphp
                        <div class="p-4 w-100">
                            <button type="button" class="btn btn-link btn-sm px-0 mb-2 d-lg-none" wire:click="$set('selectedId', null)"><i class="bi bi-arrow-left me-1"></i> Back to the list</button>

                            <div class="d-flex flex-wrap align-items-start gap-2 mb-3">
                                <div class="me-auto">
                                    <h5 class="fw-bold text-navy mb-1">{{ $e->subject ?: '(no subject)' }}</h5>
                                    <div class="small text-muted">
                                        From {{ $e->sender?->name }} ·
                                        {{ $e->isDraft() ? 'Draft saved '.$e->updated_at->format('d M Y H:i') : 'Sent '.$e->sent_at?->format('d M Y H:i') }}
                                    </div>
                                </div>
                                <span class="badge {{ $sc }} fs-6"><i class="bi {{ $si }} me-1"></i>{{ $sl }}</span>
                                <button type="button" class="btn btn-sm btn-cta" wire:click="useEmail({{ $e->id }})">
                                    <i class="bi {{ $e->isDraft() ? 'bi-pencil' : 'bi-arrow-repeat' }} me-1"></i> {{ $e->isDraft() ? 'Continue' : 'Use again' }}
                                </button>
                            </div>

                            {{-- Facts --}}
                            <div class="row g-2 mb-3">
                                <div class="col-6 col-md-3"><div class="ce-fact"><div class="small text-muted">Recipients</div><div class="fw-bold text-navy">{{ $e->recipient_count }}</div></div></div>
                                <div class="col-6 col-md-3"><div class="ce-fact"><div class="small text-muted">Delivered</div><div class="fw-bold text-success">{{ $e->sent_count }}</div></div></div>
                                <div class="col-6 col-md-3"><div class="ce-fact"><div class="small text-muted">Failed</div><div class="fw-bold {{ $e->failed_count ? 'text-danger' : 'text-navy' }}">{{ $e->failed_count }}</div></div></div>
                                <div class="col-6 col-md-3"><div class="ce-fact"><div class="small text-muted">Mode</div><div class="fw-bold text-navy small">{{ $spec['individually'] ? 'One e-mail each' : 'One e-mail to all' }}</div></div></div>
                            </div>

                            {{-- Who it was for --}}
                            <div class="mb-3">
                                <div class="ce-label mb-1">Sent to</div>
                                <div class="d-flex flex-wrap gap-1 mb-2">
                                    @foreach ((array) $spec['groups'] as $g)
                                        <span class="pill pill-muted">
                                            <i class="bi {{ $groups[$g][2] ?? 'bi-people' }}"></i> {{ $groups[$g][0] ?? $g }}
                                            @if ($g === 'stage' && isset($stages[$spec['stage']])) — {{ $stages[$spec['stage']][0] }} @endif
                                        </span>
                                    @endforeach
                                    @if ($e->expressionOfInterest)
                                        <span class="pill pill-muted"><i class="bi bi-link-45deg"></i> {{ $e->expressionOfInterest->reference }}{{ $e->expressionOfInterest->business_name ? ' · '.$e->expressionOfInterest->business_name : '' }}</span>
                                    @endif
                                    @foreach (['cc' => 'Cc', 'bcc' => 'Bcc'] as $f => $lbl)
                                        @if (filled($spec[$f])) <span class="pill pill-muted">{{ $lbl }}: {{ $spec[$f] }}</span> @endif
                                    @endforeach
                                </div>
                                @if (! empty($e->recipient_list))
                                    <details class="small">
                                        <summary class="text-muted" role="button">{{ count($e->recipient_list) }} e-mail {{ \Illuminate\Support\Str::plural('address', count($e->recipient_list)) }}</summary>
                                        <div class="mt-2 p-2 rounded-3 text-break" style="background: #f8f9fc; max-height: 160px; overflow: auto;">{{ implode(', ', $e->recipient_list) }}</div>
                                    </details>
                                @endif
                            </div>

                            {{-- Attachments --}}
                            @if (! empty($e->attachments))
                                <div class="mb-3">
                                    <div class="ce-label mb-1">Attachments</div>
                                    <div class="d-flex flex-wrap gap-2">
                                        @foreach ($e->attachments as $i => $a)
                                            <a href="{{ route('compose.email.attachment', [$e->id, $i]) }}" class="attach-chip text-decoration-none">
                                                <i class="bi bi-paperclip"></i> {{ \Illuminate\Support\Str::limit($a['name'] ?? 'file', 40) }} · {{ $size($a['size'] ?? 0) }}
                                            </a>
                                        @endforeach
                                    </div>
                                </div>
                            @endif

                            {{-- Message --}}
                            <div class="ce-label mb-1">Message <span class="text-muted text-lowercase fw-normal" style="letter-spacing: 0">— as written; {name} etc. were filled in for each person</span></div>
                            <div class="bg-white border rounded-3 p-4">{!! \App\Support\EmailRecipients::clean($e->body) !!}</div>
                        </div>
                    @else
                        <div class="m-auto text-center text-muted small p-5">
                            <i class="bi bi-envelope-open fs-1 d-block mb-2"></i>
                            Select an e-mail to see the message, who it went to and its attachments.
                        </div>
                    @endif
                </div>
            </div>
        </div>
    @endif
</div>