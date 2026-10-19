<?php

use App\Models\ExpressionOfInterest;
use App\Models\SentEmail;
use App\Support\ComposeEmailSender;
use App\Support\EmailRecipients;
use App\Support\EmailTemplates;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Livewire\Attributes\Computed;
use Livewire\Attributes\On;
use Livewire\Component;
use Livewire\WithFileUploads;

/*
| Compose Email · the e-mail being written: recipients (child component), subject, message, attachments, preview, send.
|
|   <livewire:compose-email.composer />
|     ├─ <livewire:compose-email.recipients wire:model="recipients" />   step 1
|     └─ Quill editor (wire:ignore) → $body                              step 2
|   Listens:  use-template (templates panel) · load-email (recent panel) · draft-deleted
|   Sends:    email-saved (recent panel refreshes)
|
| Sending: App\Support\ComposeEmailSender — each recipient gets their own e-mail (default) via the ComposedEmail notification.
| Attachments are stored as soon as they are picked (SentEmail::DISK, compose-email/…).
*/
new class extends Component
{
    use WithFileUploads;

    public const MAX_FILES   = 5;
    public const MAX_FILE_KB = 10240;   // 10 MB each

    protected const STARTER = '<p>Dear {name},</p><p><br></p><p>Kind regards,<br>LEHSFF Co-Investment Facility</p>';

    public array $recipients = [];
    public string $subject = '';
    public string $body = self::STARTER;

    /** Files being uploaded right now (moved to $files straight away) */
    public array $uploads = [];

    /** Stored attachments: [{name, path, size}] */
    public array $files = [];

    public bool $copyMe = false;

    /** The draft being edited (null = new e-mail) */
    public ?int $draftId = null;

    public bool $showPreview = false;

    /** Bumped when the whole form is replaced (draft loaded / new e-mail) so the recipients panel reloads */
    public int $version = 0;

    public function mount(): void
    {
        abort_unless(EmailRecipients::canCompose(auth()->user()), 403);
        $this->recipients = EmailRecipients::defaults();
    }

    /* =====================================================================
     | Templates / drafts / "use again"
     ===================================================================== */

    #[On('use-template')]
    public function useTemplate(string $key): void
    {
        $t = EmailTemplates::find($key);
        if (! $t) {
            return;
        }
        $this->subject = $t[2];
        $this->setBody($t[3]);
        $this->resetValidation(['subject', 'body']);
    }

    #[On('load-email')]
    public function loadEmail(int $id): void
    {
        $e = SentEmail::findOrFail($id);
        abort_unless($e->status !== 'draft' || $e->sender_id === auth()->id(), 403);

        $this->subject    = $e->subject;
        $this->recipients = array_merge(EmailRecipients::defaults(), (array) $e->recipients_spec, ['application_id' => $e->expression_of_interest_id]);
        $this->setBody($e->body);

        if ($e->isDraft()) {
            $this->draftId = $e->id;
            $this->files   = (array) $e->attachments;
            $msg = 'Draft loaded — carry on where you left off.';
        } else {
            $this->draftId = null;
            $this->files   = [];      // sent attachments stay with the sent e-mail
            $msg = 'Copied into a new e-mail. Attach any files again if needed.';
        }

        $this->resetValidation();
        $this->version++;
        $this->dispatch('notify', type: 'info', message: $msg);
    }

    #[On('draft-deleted')]
    public function draftDeleted(int $id): void
    {
        if ($this->draftId === $id) {
            $this->startNew();
        }
    }

    public function startNew(): void
    {
        $this->reset('subject', 'uploads', 'files', 'copyMe', 'draftId', 'showPreview');
        $this->recipients = EmailRecipients::defaults();
        $this->setBody(self::STARTER);
        $this->resetValidation();
        $this->version++;
    }

    /** Change the message and tell the editor */
    protected function setBody(string $html): void
    {
        $this->body = $html;
        $this->dispatch('ce-set-body', html: $html);
    }

    /* =====================================================================
     | Attachments
     ===================================================================== */

    public function updatedUploads(): void
    {
        $this->validate(
            ['uploads.*' => ['file', 'max:'.self::MAX_FILE_KB, 'mimes:pdf,doc,docx,xls,xlsx,csv,ppt,pptx,jpg,jpeg,png,zip']],
            ['uploads.*.max' => 'Each file must be 10 MB or smaller.', 'uploads.*.mimes' => 'Attach PDF, Office documents, images or ZIP files.'],
        );

        foreach ($this->uploads as $file) {
            if (count($this->files) >= self::MAX_FILES) {
                $this->addError('uploads', 'You can attach up to '.self::MAX_FILES.' files.');
                break;
            }
            $this->files[] = [
                'name' => $file->getClientOriginalName(),
                'path' => $file->store('compose-email/'.now()->format('Y/m'), SentEmail::DISK),
                'size' => $file->getSize(),
            ];
        }
        $this->reset('uploads');
    }

    public function removeFile(int $i): void
    {
        $f = $this->files[$i] ?? null;
        if (! $f) {
            return;
        }
        // Only delete the stored file if no saved e-mail uses it
        $used = SentEmail::whereJsonContains('attachments', ['path' => $f['path']])->where('id', '!=', $this->draftId)->exists();
        if (! $used) {
            Storage::disk(SentEmail::DISK)->delete($f['path']);
        }
        array_splice($this->files, $i, 1);
    }

    /* =====================================================================
     | Preview
     ===================================================================== */

    /** First person it goes to (for the preview) */
    #[Computed]
    public function sample(): ?array
    {
        return EmailRecipients::resolve($this->recipients)->first()
            ?? ['email' => auth()->user()->email, 'name' => auth()->user()->name, 'eoi' => null];
    }

    #[Computed]
    public function linked(): ?ExpressionOfInterest
    {
        return filled($this->recipients['application_id'] ?? null) ? ExpressionOfInterest::find($this->recipients['application_id']) : null;
    }

    public function preview(): void
    {
        $this->validateMessage();
        $this->showPreview = true;
    }

    /* =====================================================================
     | Save / send
     ===================================================================== */

    protected function validateMessage(): void
    {
        $this->body = EmailRecipients::clean($this->body);

        $this->validate([
            'subject' => ['required', 'string', 'max:200'],
            'body'    => ['required', 'string', function ($attr, $value, $fail) {
                if (trim(html_entity_decode(strip_tags(str_replace(['{name}', 'Dear', 'Kind regards,', 'LEHSFF Co-Investment Facility'], '', $value)))) === '') {
                    $fail('Write your message.');
                }
            }],
        ], ['subject.required' => 'Add a subject.']);
    }

    /** Create or update the SentEmail row */
    protected function store(string $status): SentEmail
    {
        $data = [
            'sender_id'                 => auth()->id(),
            'expression_of_interest_id' => $this->recipients['application_id'] ?: null,
            'subject'                   => Str::limit(trim($this->subject), 200, ''),
            'body'                      => EmailRecipients::clean($this->body),
            'recipients_spec'           => array_merge(EmailRecipients::defaults(), $this->recipients),
            'attachments'               => array_values($this->files),
            'status'                    => $status,
        ];

        $draft = $this->draftId ? SentEmail::where('sender_id', auth()->id())->where('status', 'draft')->find($this->draftId) : null;
        if ($draft) {
            $draft->update($data);
            return $draft;
        }

        return SentEmail::create($data);
    }

    public function saveDraft(): void
    {
        $this->validate(['subject' => ['nullable', 'string', 'max:200']]);

        $draft = $this->store('draft');
        $this->draftId = $draft->id;

        $this->dispatch('email-saved');
        $this->dispatch('notify', type: 'success', message: 'Draft saved.');
    }

    public function send(): void
    {
        abort_unless(EmailRecipients::canCompose(auth()->user()), 403);
        $this->validateMessage();

        $bad = collect(['to', 'cc', 'bcc'])->flatMap(fn ($f) => EmailRecipients::invalid($this->recipients[$f] ?? ''));
        if ($bad->isNotEmpty()) {
            $this->addError('recipients', 'Fix these addresses first: '.$bad->implode(', ').'.');
            return;
        }

        $count = EmailRecipients::resolve($this->recipients)->count()
            + count(EmailRecipients::parse($this->recipients['cc'])) + count(EmailRecipients::parse($this->recipients['bcc']));
        if ($count === 0) {
            $this->addError('recipients', 'Choose who should receive the e-mail.');
            return;
        }
        if ($count > EmailRecipients::MAX_RECIPIENTS) {
            $this->addError('recipients', 'That is '.$count.' people — the limit is '.EmailRecipients::MAX_RECIPIENTS.' per e-mail.');
            return;
        }

        $record = $this->store('draft');                 // saved first, so nothing is lost if sending fails
        $result = ComposeEmailSender::send($record, auth()->user(), $this->copyMe);

        $this->showPreview = false;
        $this->dispatch('email-saved');

        if ($result['failed'] === 0) {
            $this->dispatch('notify', type: 'success', message: 'E-mail sent to '.$result['sent'].' '.Str::plural('recipient', $result['sent']).'.');
            $this->startNew();
        } elseif ($result['sent'] > 0) {
            $this->dispatch('notify', type: 'warning', message: "Sent to {$result['sent']}, but {$result['failed']} could not be sent. Details are in the log.");
            $this->startNew();
        } else {
            $this->draftId = null;
            $this->dispatch('notify', type: 'error', message: 'The e-mail could not be sent. Check the mail settings (laravel.log) and try again.');
        }
    }
};
?>

@php
    $fields = \App\Support\EmailRecipients::MERGE_FIELDS;
    $size   = fn ($b) => $b >= 1048576 ? round($b / 1048576, 1).' MB' : max(1, round($b / 1024)).' KB';
@endphp

<div>
    @if ($draftId)
        <div class="alert alert-info small py-2 d-flex align-items-center">
            <i class="bi bi-pencil me-2"></i> <span class="me-auto">You are editing a saved draft.</span>
            <button type="button" class="btn btn-link btn-sm p-0" wire:click="startNew">Start a new e-mail</button>
        </div>
    @endif

    {{-- ---------- 1. Recipients ---------- --}}
    @error('recipients') <div class="alert alert-danger small py-2"><i class="bi bi-exclamation-triangle me-1"></i> {{ $message }}</div> @enderror
    <livewire:compose-email.recipients wire:model="recipients" :key="'recipients-'.$version" />

    {{-- ---------- 2. Message ---------- --}}
    <div class="ce-card p-3 mb-3">
        <span class="ce-label d-block mb-2"><span class="badge rounded-pill text-bg-dark me-1">2</span> Message</span>

        <div class="mb-3">
            <label class="form-label small mb-1" for="ce-subject">Subject <span class="text-danger">*</span></label>
            <input type="text" class="form-control @error('subject') is-invalid @enderror" id="ce-subject" wire:model.blur="subject" maxlength="200" placeholder="e.g. Reminder: documents needed for your application">
            @error('subject') <div class="text-danger small mt-1">{{ $message }}</div> @enderror
        </div>

        {{-- Editor: Quill keeps $body up to date; the server can replace it with "ce-set-body" --}}
        <div x-data="{
                q: null,
                init() {
                    this.q = new Quill(this.$refs.editor, {
                        theme: 'snow',
                        placeholder: 'Write your message…',
                        modules: { toolbar: [[{ header: [2, 3, false] }], ['bold', 'italic', 'underline'], [{ list: 'ordered' }, { list: 'bullet' }], ['link'], ['clean']] },
                    });
                    this.q.root.innerHTML = $wire.body;
                    this.q.on('text-change', () => { $wire.body = this.q.root.innerHTML; });
                    $wire.on('ce-set-body', ({ html }) => { this.q.root.innerHTML = html; });
                },
                insert(tag) {
                    const r = this.q.getSelection(true);
                    this.q.insertText(r ? r.index : this.q.getLength() - 1, tag);
                },
             }">
            <div class="d-flex flex-wrap align-items-center gap-1 mb-2">
                <span class="small text-muted me-1">Insert:</span>
                @foreach ($fields as $f)
                    <button type="button" class="badge text-bg-light border merge-tag" x-on:click="insert('{{ $f }}')">{{ $f }}</button>
                @endforeach
                <span class="small text-muted ms-1">— filled in for each recipient</span>
            </div>
            <div wire:ignore><div x-ref="editor" id="editor"></div></div>
        </div>
        @error('body') <div class="text-danger small mt-1">{{ $message }}</div> @enderror

        {{-- Attachments --}}
        <div class="mt-3">
            <label class="form-label small mb-1" for="ce-files">Attachments <span class="text-muted">(up to {{ $this::MAX_FILES }} files · 10 MB each)</span></label>
            @if (count($files) < $this::MAX_FILES)
                <input type="file" class="form-control form-control-sm @error('uploads') is-invalid @enderror @error('uploads.*') is-invalid @enderror" id="ce-files" wire:model="uploads" multiple
                       accept=".pdf,.doc,.docx,.xls,.xlsx,.csv,.ppt,.pptx,.jpg,.jpeg,.png,.zip">
            @endif
            <div wire:loading wire:target="uploads" class="small text-muted mt-1"><span class="spinner-border spinner-border-sm me-1"></span> Uploading…</div>
            @error('uploads') <div class="text-danger small mt-1">{{ $message }}</div> @enderror
            @error('uploads.*') <div class="text-danger small mt-1">{{ $message }}</div> @enderror
            @if ($files)
                <div class="d-flex flex-wrap gap-2 mt-2">
                    @foreach ($files as $i => $f)
                        <span class="attach-chip" wire:key="file-{{ md5($f['path']) }}">
                            <i class="bi bi-paperclip"></i> {{ \Illuminate\Support\Str::limit($f['name'], 40) }} · {{ $size($f['size'] ?? 0) }}
                            <button type="button" class="btn btn-link btn-sm p-0 text-danger" title="Remove" wire:click="removeFile({{ $i }})"><i class="bi bi-x-lg"></i></button>
                        </span>
                    @endforeach
                </div>
            @endif
        </div>
    </div>

    {{-- ---------- 3. Send ---------- --}}
    <div class="ce-card p-3 d-flex flex-wrap align-items-center gap-2">
        <div class="form-check mb-0 me-auto">
            <input class="form-check-input" type="checkbox" id="ce-copy" wire:model="copyMe">
            <label class="form-check-label small" for="ce-copy">Send me a copy</label>
        </div>
        <button type="button" class="btn btn-outline-secondary" wire:click="preview" wire:loading.attr="disabled" wire:target="preview,send,saveDraft,uploads">
            <i class="bi bi-eye me-1"></i> Preview
        </button>
        <button type="button" class="btn btn-outline-secondary" wire:click="saveDraft" wire:loading.attr="disabled" wire:target="preview,send,saveDraft,uploads">
            <span wire:loading.remove wire:target="saveDraft"><i class="bi bi-save me-1"></i> Save draft</span>
            <span wire:loading wire:target="saveDraft"><span class="spinner-border spinner-border-sm me-1"></span> Saving…</span>
        </button>
        <button type="button" class="btn btn-cta" wire:click="send" wire:confirm="Send this e-mail now?" wire:loading.attr="disabled" wire:target="preview,send,saveDraft,uploads">
            <span wire:loading.remove wire:target="send"><i class="bi bi-send me-1"></i> Send e-mail</span>
            <span wire:loading wire:target="send"><span class="spinner-border spinner-border-sm me-1"></span> Sending…</span>
        </button>
    </div>

    {{-- ---------- Preview (no Bootstrap JS needed) ---------- --}}
    @if ($showPreview)
        @php
            $sample  = $this->sample;
            $linked  = $this->linked;
            $pSubj   = \App\Support\EmailRecipients::merge($subject, $sample, $linked, html: false);
            $pBody   = \App\Support\EmailRecipients::merge($body, $sample, $linked);
            $todo    = collect([$subject, strip_tags($body)])->flatMap(fn ($t) => preg_match_all('/\[[^\]\n]{1,40}\]/', $t, $mm) ? $mm[0] : [])->unique()->values();
        @endphp
        <div class="modal d-block" tabindex="-1" role="dialog" aria-modal="true" style="background: rgba(13, 26, 58, .45);" wire:keydown.escape.window="$set('showPreview', false)">
            <div class="modal-dialog modal-lg modal-dialog-scrollable">
                <div class="modal-content">
                    <div class="modal-header">
                        <h5 class="modal-title"><i class="bi bi-eye me-1"></i> Preview</h5>
                        <button type="button" class="btn-close" wire:click="$set('showPreview', false)" aria-label="Close"></button>
                    </div>
                    <div class="modal-body" style="background: #edf2f7;">
                        @if ($todo->isNotEmpty())
                            <div class="alert alert-warning small py-2"><i class="bi bi-exclamation-triangle me-1"></i> Still to fill in: {{ $todo->implode(', ') }}</div>
                        @endif
                        <div class="mx-auto bg-white rounded-3 shadow-sm" style="max-width: 570px;">
                            <div class="text-center py-3 border-bottom"><img src="{{ asset('/images/logo.jpg') }}" alt="CAFI" style="max-height: 50px;"></div>
                            <div class="p-4">
                                <div class="small text-muted mb-1">Subject</div>
                                <h6 class="fw-bold text-navy mb-3">{{ $pSubj }}</h6>
                                <div class="small">{!! $pBody !!}</div>
                                @if ($files)
                                    <hr><div class="small text-muted"><i class="bi bi-paperclip"></i> {{ collect($files)->pluck('name')->implode(', ') }}</div>
                                @endif
                            </div>
                        </div>
                        <p class="text-center small text-muted mt-3 mb-0">As {{ $sample['name'] ?: $sample['email'] }} will see it ({{ $sample['email'] }}).</p>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-outline-secondary" wire:click="$set('showPreview', false)">Keep editing</button>
                        <button type="button" class="btn btn-cta" wire:click="send" wire:confirm="Send this e-mail now?" wire:loading.attr="disabled" wire:target="send">
                            <span wire:loading.remove wire:target="send"><i class="bi bi-send me-1"></i> Send e-mail</span>
                            <span wire:loading wire:target="send"><span class="spinner-border spinner-border-sm me-1"></span> Sending…</span>
                        </button>
                    </div>
                </div>
            </div>
        </div>
    @endif
</div>