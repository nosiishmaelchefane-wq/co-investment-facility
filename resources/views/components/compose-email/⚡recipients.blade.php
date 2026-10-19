<?php

use App\Models\ExpressionOfInterest;
use App\Support\EmailRecipients;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Modelable;
use Livewire\Component;

/*
| Compose Email · step 1 — who should receive it.
| Bound to the composer:  <livewire:compose-email.recipients wire:model="recipients" />
| $value = ['groups' => [...], 'stage' => '', 'application_id' => null, 'to' => '', 'cc' => '', 'bcc' => '', 'individually' => true]
*/
new class extends Component
{
    #[Modelable]
    public array $value = [];

    public bool $showCc = false;

    public function mount(): void
    {
        $this->value  = array_merge(EmailRecipients::defaults(), $this->value);
        $this->showCc = filled($this->value['cc']) || filled($this->value['bcc']);
    }

    /** Ticking "Applicants at a gate" without a gate, or "This application" without one, is pointless — keep things tidy */
    public function updatedValue($v, $key): void
    {
        if ($key === 'stage' && filled($v) && ! in_array('stage', $this->value['groups'], true)) {
            $this->value['groups'][] = 'stage';
        }
        if ($key === 'application_id' && blank($v)) {
            $this->value['groups'] = array_values(array_diff($this->value['groups'], ['applicant']));
        }
    }

    #[Computed(persist: true, seconds: 60)]
    public function groupCounts(): array
    {
        return EmailRecipients::groupCounts();
    }

    #[Computed(persist: true, seconds: 60)]
    public function stageCounts(): array
    {
        return EmailRecipients::stageCounts();
    }

    /** Applications to link the e-mail to */
    #[Computed]
    public function applications()
    {
        return ExpressionOfInterest::where('status', '!=', ExpressionOfInterest::STATUS_DRAFT)
            ->latest('updated_at')->limit(300)->get(['id', 'reference', 'business_name']);
    }

    /** Everyone it will go to (deduplicated) */
    #[Computed]
    public function total(): int
    {
        $rows = EmailRecipients::resolve($this->value);

        if ($this->value['individually']) {
            foreach ([...EmailRecipients::parse($this->value['cc']), ...EmailRecipients::parse($this->value['bcc'])] as $e) {
                $rows->put(strtolower($e), true);
            }
            return $rows->count();
        }

        return $rows->count() + count(EmailRecipients::parse($this->value['cc'])) + count(EmailRecipients::parse($this->value['bcc']));
    }
};
?>

@php
    $groups = \App\Support\EmailRecipients::GROUPS;
    $counts = $this->groupCounts;
    $stages = \App\Support\EmailRecipients::stages();
    $sCount = $this->stageCounts;
    $bad    = collect(['to', 'cc', 'bcc'])->mapWithKeys(fn ($f) => [$f => \App\Support\EmailRecipients::invalid($value[$f] ?? '')])->filter();
@endphp

<div class="ce-card p-3 mb-3">
    <div class="d-flex align-items-center mb-2">
        <span class="ce-label me-auto"><span class="badge rounded-pill text-bg-dark me-1">1</span> Who should receive it?</span>
        <span class="small text-muted">
            <span wire:loading wire:target="value" class="spinner-border spinner-border-sm me-1"></span>
            <i class="bi bi-people me-1"></i><strong>{{ number_format($this->total) }}</strong> {{ \Illuminate\Support\Str::plural('recipient', $this->total) }}
        </span>
    </div>

    <div class="row g-2 mb-3">
        @foreach ($groups as $key => [$label, $hint, $icon])
            @php
                $n = match ($key) {
                    'stage'     => filled($value['stage']) ? ($sCount[$value['stage']] ?? 0) : null,
                    'applicant' => filled($value['application_id']) ? 1 : null,
                    default     => $counts[$key] ?? 0,
                };
                $disabled = $key === 'applicant' && blank($value['application_id']);
            @endphp
            <div class="col-6 col-md-4" wire:key="grp-{{ $key }}">
                <input type="checkbox" class="btn-check" id="grp-{{ $key }}" value="{{ $key }}" wire:model.live="value.groups" autocomplete="off" @disabled($disabled)>
                <label class="group-option d-block {{ $disabled ? 'opacity-50' : '' }}" for="grp-{{ $key }}" title="{{ $disabled ? 'Link an application below first' : '' }}">
                    <div class="d-flex align-items-center gap-2">
                        <i class="bi {{ $icon }} text-navy"></i>
                        <span class="fw-semibold small text-navy">{{ $label }}</span>
                    </div>
                    <div class="count mt-1">{{ $hint }}@if ($n !== null) · {{ $n }} {{ $n === 1 ? 'person' : 'people' }}@endif</div>
                </label>
            </div>
        @endforeach
    </div>

    <div class="row g-2 mb-3">
        <div class="col-md-6">
            <label class="form-label small mb-1" for="ce-stage">Applicants at gate</label>
            <select class="form-select form-select-sm" id="ce-stage" wire:model.live="value.stage">
                <option value="">— Choose a gate —</option>
                @foreach ($stages as $key => [$label])
                    <option value="{{ $key }}">{{ $label }} ({{ $sCount[$key] ?? 0 }})</option>
                @endforeach
            </select>
        </div>
        <div class="col-md-6">
            <label class="form-label small mb-1" for="ce-app">About an application <span class="text-muted">(optional)</span></label>
            <select class="form-select form-select-sm" id="ce-app" wire:model.live="value.application_id">
                <option value="">— Not linked to an application —</option>
                @foreach ($this->applications as $a)
                    <option value="{{ $a->id }}">{{ $a->reference }}{{ $a->business_name ? ' · '.$a->business_name : '' }}</option>
                @endforeach
            </select>
            <div class="form-text small">Fills {business}, {reference}, {status} and {link} from this application for everyone.</div>
        </div>
    </div>

    <div class="mb-2">
        <label class="form-label small mb-1" for="ce-to">To (other e-mail addresses)</label>
        <div class="input-group input-group-sm">
            <span class="input-group-text"><i class="bi bi-envelope"></i></span>
            <input type="text" class="form-control @if (isset($bad['to'])) is-invalid @endif" id="ce-to" wire:model.live.debounce.600ms="value.to" placeholder="name@example.co.ls, another@example.com">
            <button class="btn btn-outline-secondary" type="button" wire:click="$toggle('showCc')">Cc / Bcc</button>
        </div>
        @if (isset($bad['to']))
            <div class="text-danger small mt-1">Not an e-mail address: {{ implode(', ', $bad['to']) }}</div>
        @else
            <div class="form-text small">Separate several addresses with a comma.</div>
        @endif
    </div>

    @if ($showCc)
        <div class="row g-2">
            @foreach (['cc' => ['Cc', 'Copied'], 'bcc' => ['Bcc', 'Hidden copy']] as $f => [$lbl, $ph])
                <div class="col-md-6">
                    <label class="form-label small mb-1" for="ce-{{ $f }}">{{ $lbl }}</label>
                    <input type="text" class="form-control form-control-sm @if (isset($bad[$f])) is-invalid @endif" id="ce-{{ $f }}" wire:model.live.debounce.600ms="value.{{ $f }}" placeholder="{{ $ph }}">
                    @if (isset($bad[$f])) <div class="text-danger small mt-1">Not an e-mail address: {{ implode(', ', $bad[$f]) }}</div> @endif
                </div>
            @endforeach
        </div>
    @endif

    <div class="form-check form-switch mt-3 mb-0">
        <input class="form-check-input" type="checkbox" role="switch" id="ce-individually" wire:model.live="value.individually">
        <label class="form-check-label small" for="ce-individually">
            Send each person their own e-mail
            <span class="text-muted">{{ $value['individually'] ? '(recommended — merge fields are personal and nobody sees anyone else\'s address; Cc/Bcc get their own copy)' : '(one e-mail to everyone — all recipients see each other in "To")' }}</span>
        </label>
    </div>
</div>