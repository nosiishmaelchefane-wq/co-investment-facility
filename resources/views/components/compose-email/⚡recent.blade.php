<?php

use App\Models\SentEmail;
use Livewire\Attributes\Computed;
use Livewire\Attributes\On;
use Livewire\Component;

/*
| Compose Email · my drafts + recently sent e-mails.
|   Draft → "Continue" loads it into the composer (sends "load-email").
|   Sent  → "Use again" copies subject, message and recipients into a new e-mail.
| Refreshes when the composer sends "email-saved".
*/
new class extends Component
{
    public int $limit = 6;

    public ?int $openId = null;

    #[On('email-saved')]
    public function refresh(): void
    {
        unset($this->drafts, $this->sent);
    }

    #[Computed]
    public function drafts()
    {
        return SentEmail::where('sender_id', auth()->id())->where('status', 'draft')->latest('updated_at')->get(['id', 'subject', 'updated_at']);
    }

    #[Computed]
    public function sent()
    {
        return SentEmail::with('sender:id,name')->where('status', '!=', 'draft')->latest('sent_at')->limit($this->limit)->get();
    }

    public function more(): void
    {
        $this->limit += 10;
        unset($this->sent);
    }

    public function toggle(int $id): void
    {
        $this->openId = $this->openId === $id ? null : $id;
    }

    public function deleteDraft(int $id): void
    {
        SentEmail::where('sender_id', auth()->id())->where('status', 'draft')->findOrFail($id)->delete();
        unset($this->drafts);
        $this->dispatch('draft-deleted', id: $id);
    }
};
?>

@php $S = \App\Models\SentEmail::STATUSES; @endphp

<div>
    @if ($this->drafts->isNotEmpty())
        <div class="ce-card p-3 mb-3">
            <span class="ce-label d-block mb-2"><i class="bi bi-pencil me-1"></i> My drafts</span>
            @foreach ($this->drafts as $d)
                <div class="recent-item py-2 d-flex align-items-center gap-2" wire:key="draft-{{ $d->id }}">
                    <div class="me-auto min-w-0">
                        <div class="fw-semibold small text-navy text-truncate">{{ $d->subject ?: '(no subject)' }}</div>
                        <div class="small text-muted">Saved {{ $d->updated_at->diffForHumans() }}</div>
                    </div>
                    <button type="button" class="btn btn-sm btn-outline-secondary" wire:click="$dispatch('load-email', { id: {{ $d->id }} })">Continue</button>
                    <button type="button" class="btn btn-sm btn-icon btn-icon-danger" title="Delete draft" wire:click="deleteDraft({{ $d->id }})" wire:confirm="Delete this draft?"><i class="bi bi-trash"></i></button>
                </div>
            @endforeach
        </div>
    @endif

    <div class="ce-card p-3" id="sent-emails">
        <div class="d-flex align-items-center mb-2">
            <span class="ce-label me-auto"><i class="bi bi-clock-history me-1"></i> Recently sent</span>
            <button type="button" class="btn btn-link btn-sm p-0 text-decoration-none" wire:click="$dispatch('open-email-history')">View all</button>
        </div>
        @forelse ($this->sent as $e)
            @php [$sl, $sc, $si] = $S[$e->status] ?? [$e->status, 'status-draft', 'bi-circle']; @endphp
            <div class="recent-item py-2" wire:key="sent-{{ $e->id }}">
                <div class="d-flex align-items-start gap-2" role="button" wire:click="toggle({{ $e->id }})">
                    <div class="me-auto min-w-0">
                        <div class="fw-semibold small text-navy text-truncate">{{ $e->subject }}</div>
                        <div class="small text-muted">
                            <i class="bi bi-people me-1"></i>{{ $e->sent_count }} of {{ $e->recipient_count }} {{ \Illuminate\Support\Str::plural('recipient', $e->recipient_count) }}
                            · {{ $e->sender?->name }}
                        </div>
                    </div>
                    <div class="text-end">
                        <span class="badge {{ $sc }}"><i class="bi {{ $si }}"></i> {{ $sl }}</span>
                        <div class="small text-muted mt-1">{{ $e->sent_at?->diffForHumans(short: true) }}</div>
                    </div>
                </div>
                @if ($openId === $e->id)
                    <div class="small mt-2 p-2 rounded-3" style="background: #f8f9fc;">
                        <div class="text-muted mb-1">Sent {{ $e->sent_at?->format('d M Y H:i') }}@if ($e->failed_count) · <span class="text-danger">{{ $e->failed_count }} could not be sent (see laravel.log)</span>@endif</div>
                        <div class="text-muted mb-2 text-break">To: {{ \Illuminate\Support\Str::limit(implode(', ', (array) $e->recipient_list), 300) }}</div>
                        @if (! empty($e->attachments))
                            <div class="text-muted mb-2"><i class="bi bi-paperclip"></i> {{ collect($e->attachments)->pluck('name')->implode(', ') }}</div>
                        @endif
                        <button type="button" class="btn btn-sm btn-outline-secondary" wire:click="$dispatch('load-email', { id: {{ $e->id }} })"><i class="bi bi-arrow-repeat me-1"></i> Use again</button>
                        <button type="button" class="btn btn-sm btn-link text-decoration-none" wire:click="$dispatch('open-email-history', { id: {{ $e->id }} })">Open</button>
                    </div>
                @endif
            </div>
        @empty
            <p class="small text-muted mb-0">Nothing sent yet.</p>
        @endforelse
        @if ($this->sent->count() >= $limit)
            <button type="button" class="btn btn-link btn-sm p-0 mt-2 text-decoration-none" wire:click="$dispatch('open-email-history')">See all sent e-mails <i class="bi bi-arrow-right"></i></button>
        @endif
    </div>
</div>