{{--
    G4 · signing outside the system for one authority (no e-mail is sent). Included from ⚡authorise-application (no $this here).
    Vars: $role ('md'|'ps'), $a (?Authorisation), $can (bool), $signing (array), $decidedRole (bool)
--}}
@php
    $A        = \App\Models\Authorisation::class;
    $title    = $A::AUTHORITIES[$role][1];
    $sent     = (bool) $a?->sentForSigning($role);
    $signed   = (bool) $a?->hasSignedCopy($role);
    $reqPath  = $a?->{"{$role}_request_path"};
    $s        = $signing[$role] ?? [];
@endphp

<div class="border rounded-3 p-3 mb-3 bg-light">
    <div class="row g-3">
        {{-- ============ Step 1 · send for signature ============ --}}
        <div class="col-lg-7">
            <div class="d-flex align-items-center gap-2 mb-2">
                <span class="pill {{ $sent ? 'pill-ok' : ($signed ? 'pill-muted' : 'pill-warn') }}"><i class="bi {{ $sent ? 'bi-envelope-check' : 'bi-envelope' }}"></i> 1</span>
                <span class="fw-semibold text-navy small">Attach the document and send it to the {{ $title }} for signing</span>
            </div>

            @if ($sent)
                <div class="small mb-2">
                    Sent to <strong>{{ $a->{"{$role}_signer_name"} }}</strong> ({{ $a->{"{$role}_signer_email"} }})
                    on {{ $a->{"{$role}_sent_at"}->format('d M Y') }}
                    @php $sender = $role === 'md' ? $a->mdSender : $a->psSender; @endphp
                    @if ($sender) by {{ $sender->name }} @endif.
                    @if ($reqPath) <a href="{{ route('gate4.file', [$a, $role.'_request']) }}" class="text-decoration-none ms-1"><i class="bi bi-paperclip"></i> {{ \Illuminate\Support\Str::limit($a->{"{$role}_request_name"}, 40) }}</a> @endif
                </div>
            @elseif (! $can)
                <div class="small text-muted mb-2">{{ $signed ? 'Sent outside the system.' : 'Not sent yet.' }}</div>
            @endif

            @if ($can && ! $signed && ! $decidedRole)
                <div class="row g-2">
                    <div class="col-md-6">
                        <label class="form-label small fw-semibold mb-1">Name <span class="req">*</span></label>
                        <input type="text" class="form-control form-control-sm @error('signing.'.$role.'.name') is-invalid @enderror" wire:model="signing.{{ $role }}.name" placeholder="{{ $A::AUTHORITIES[$role][0] }}">
                        @error('signing.'.$role.'.name') <div class="invalid-feedback">{{ $message }}</div> @enderror
                    </div>
                    <div class="col-md-6">
                        <label class="form-label small fw-semibold mb-1">E-mail <span class="req">*</span></label>
                        <input type="email" class="form-control form-control-sm @error('signing.'.$role.'.email') is-invalid @enderror" wire:model="signing.{{ $role }}.email">
                        @error('signing.'.$role.'.email') <div class="invalid-feedback">{{ $message }}</div> @enderror
                    </div>
                    <div class="col-12">
                        <label class="form-label small fw-semibold mb-1">Document to be signed @unless ($reqPath) <span class="req">*</span> @endunless</label>
                        <input type="file" class="form-control form-control-sm @error('requestFile.'.$role) is-invalid @enderror" wire:model="requestFile.{{ $role }}" accept=".pdf,.docx,.doc">
                        <div class="form-text">The authorisation form / memo for signature (PDF or Word). @if ($reqPath) Leave empty to keep the current document. @endif</div>
                        @error('requestFile.'.$role) <div class="invalid-feedback">{{ $message }}</div> @enderror
                    </div>
                    <div class="col-12">
                        <label class="form-label small fw-semibold mb-1">Note (optional)</label>
                        <textarea rows="2" class="form-control form-control-sm" wire:model="signing.{{ $role }}.message" placeholder="e.g. hand-delivered to the {{ $title }}'s office, follow up on Friday"></textarea>
                    </div>
                    <div class="col-12 d-flex">
                        <button type="button" class="btn btn-sm {{ $sent ? 'btn-outline-secondary' : 'btn-brand' }} ms-auto" wire:click="markSent('{{ $role }}')"
                                wire:loading.attr="disabled" wire:target="markSent('{{ $role }}'),requestFile.{{ $role }}">
                            <span wire:loading.remove wire:target="markSent('{{ $role }}')"><i class="bi bi-check2-square me-1"></i>{{ $sent ? 'Update' : 'Save & mark as sent for signing' }}</span>
                            <span wire:loading wire:target="markSent('{{ $role }}')"><span class="spinner-border spinner-border-sm me-1"></span> Saving…</span>
                        </button>
                    </div>
                </div>
            @endif
        </div>

        {{-- ============ Step 2 · signed copy ============ --}}
        <div class="col-lg-5">
            <div class="d-flex align-items-center gap-2 mb-2">
                <span class="pill {{ $signed ? 'pill-ok' : 'pill-warn' }}"><i class="bi {{ $signed ? 'bi-file-earmark-check' : 'bi-hourglass-split' }}"></i> 2</span>
                <span class="fw-semibold text-navy small">Upload the signed copy</span>
            </div>

            @if ($signed)
                <div class="file-row mb-0">
                    <span class="file-row-icon"><i class="bi bi-file-earmark-check"></i></span>
                    <div class="flex-grow-1 small overflow-hidden">
                        <a href="{{ route('gate4.file', [$a, $role.'_signed']) }}" class="fw-semibold text-navy text-decoration-none text-truncate d-block">{{ $a->{"{$role}_signed_name"} }}</a>
                        <div class="text-muted">Signed by {{ $a->signerName($role) }} · uploaded {{ $a->{"{$role}_signed_at"}?->format('d M Y') }}</div>
                    </div>
                    <a href="{{ route('gate4.file', [$a, $role.'_signed']) }}" class="btn btn-icon" title="Download"><i class="bi bi-download"></i></a>
                    @if ($can && ! $decidedRole)
                        <button type="button" class="btn btn-icon btn-icon-danger" title="Remove" wire:click="removeSigned('{{ $role }}')" wire:confirm="Remove the signed copy?"><i class="bi bi-trash"></i></button>
                    @endif
                </div>
            @elseif ($can && ! $decidedRole)
                <span wire:loading wire:target="signedFile.{{ $role }}" class="small text-muted"><span class="spinner-border spinner-border-sm me-1"></span> Uploading…</span>
                <div wire:loading.remove wire:target="signedFile.{{ $role }}">
                    <input type="file" class="form-control form-control-sm @error('signedFile.'.$role) is-invalid @enderror" wire:model="signedFile.{{ $role }}" accept=".pdf,.jpg,.jpeg,.png">
                    <div class="form-text">When the signed document comes back — PDF, or a clear scan / photo.</div>
                    @error('signedFile.'.$role) <div class="invalid-feedback">{{ $message }}</div> @enderror
                </div>
            @else
                <div class="small text-muted">{{ $sent ? 'Waiting for the signed copy.' : '—' }}</div>
            @endif
        </div>
    </div>
</div>