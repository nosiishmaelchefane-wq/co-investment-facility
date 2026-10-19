<x-app-layout>
    {{--
        Compose Email — GET /compose-email (ComposeEmailController@index, staff only).
        The page is made of small Livewire components in resources/views/components/compose-email/:
          composer    → the e-mail being written (contains "recipients"), preview, save draft, send
          recipients  → step 1: groups, gate, linked application, To / Cc / Bcc, one-each switch
          templates   → ready-made messages (App\Support\EmailTemplates)
          recent      → my drafts + recently sent e-mails ("Continue" / "Use again" / "View all")
          history     → full-screen drawer with every e-mail: search, filters, message, recipients, attachments
        Sending: App\Support\ComposeEmailSender → App\Notifications\ComposedEmail (Laravel's default mail layout).
        Quill 2 must load before the components (the editor starts when Livewire starts).
    --}}

    <link href="https://cdn.jsdelivr.net/npm/quill@2.0.2/dist/quill.snow.css" rel="stylesheet">
    <script src="https://cdn.jsdelivr.net/npm/quill@2.0.2/dist/quill.js"></script>

    <div class="container-fluid p-3 compose-email">
        <style>
            .compose-email .ce-card { border: 1px solid var(--cif-border, #e2e8f0); border-radius: .85rem; background: #fff; }
            .compose-email .ce-label { font-size: .72rem; font-weight: 700; letter-spacing: .06em; text-transform: uppercase; color: #64748b; }
            .compose-email .group-option { border: 1px solid var(--cif-border, #e2e8f0); border-radius: .65rem; padding: .6rem .75rem; cursor: pointer; transition: all .15s ease; height: 100%; }
            .compose-email .group-option:hover { border-color: var(--cif-accent, #089E49); background: rgba(8, 158, 73, .04); }
            .compose-email .btn-check:checked + .group-option { border-color: var(--cif-accent, #089E49); background: rgba(8, 158, 73, .08); box-shadow: 0 0 0 1px var(--cif-accent, #089E49) inset; }
            .compose-email .btn-check:disabled + .group-option { cursor: not-allowed; }
            .compose-email .group-option .count { font-size: .72rem; color: #64748b; }
            .compose-email .ql-toolbar.ql-snow { border-radius: .6rem .6rem 0 0; border-color: var(--cif-border, #e2e8f0); background: #f8f9fc; }
            .compose-email .ql-container.ql-snow { border-radius: 0 0 .6rem .6rem; border-color: var(--cif-border, #e2e8f0); font-family: inherit; font-size: .95rem; }
            .compose-email #editor { min-height: 280px; }
            .compose-email .template-item { cursor: pointer; border-radius: .55rem; padding: .55rem .7rem; }
            .compose-email .template-item:hover { background: rgba(41, 49, 125, .06); }
            .compose-email .recent-item + .recent-item { border-top: 1px dashed var(--cif-border, #e2e8f0); }
            .compose-email .attach-chip { display: inline-flex; align-items: center; gap: .35rem; font-size: .8rem; background: #f0f2fa; border-radius: 2rem; padding: .25rem .65rem; }
            .compose-email .merge-tag { font-size: .72rem; cursor: pointer; }
            .compose-email .min-w-0 { min-width: 0; }

            /* Full-screen "All e-mails" drawer */
            .compose-email .ce-drawer { position: fixed; inset: 0; z-index: 1060; display: flex; flex-direction: column; background: #f4f6fb; animation: ceSlide .2s ease-out; }
            @keyframes ceSlide { from { transform: translateX(40px); opacity: 0; } to { transform: none; opacity: 1; } }
            .compose-email .ce-drawer-head { background: #fff; border-bottom: 1px solid var(--cif-border, #e2e8f0); }
            .compose-email .ce-drawer-body { flex: 1 1 auto; min-height: 0; }
            .compose-email .ce-drawer-list { width: 100%; max-width: 440px; overflow-y: auto; flex-shrink: 0; }
            @media (max-width: 991.98px) { .compose-email .ce-drawer-list { max-width: none; } }
            .compose-email .ce-drawer-detail { overflow-y: auto; }
            .compose-email .ce-row { background: transparent; border-bottom: 1px solid var(--cif-border, #e2e8f0) !important; }
            .compose-email .ce-row:hover { background: rgba(41, 49, 125, .04); }
            .compose-email .ce-row.is-active { background: rgba(8, 158, 73, .08); box-shadow: inset 3px 0 0 var(--cif-accent, #089E49); }
            .compose-email .ce-fact { background: #fff; border: 1px solid var(--cif-border, #e2e8f0); border-radius: .65rem; padding: .5rem .75rem; }
        </style>

        {{-- ============================ HEADER ============================ --}}
        <div class="d-flex flex-wrap align-items-center gap-3 mb-4">
            <div class="me-auto">
                <h2 class="page-heading mb-1">Compose Email</h2>
                <p class="page-subtitle mb-0">Send an e-mail to applicants, the Investment Committee or staff. It is sent with the standard CAFI e-mail layout.</p>
            </div>
            <button type="button" class="btn btn-outline-secondary btn-sm" onclick="Livewire.dispatch('open-email-history')"><i class="bi bi-clock-history me-1"></i> All e-mails</button>
        </div>

        <div class="row g-3">
            <div class="col-xl-8">
                <livewire:compose-email.composer />
            </div>

            <div class="col-xl-4">
                <livewire:compose-email.templates />

                <div class="ce-card p-3 mb-3">
                    <span class="ce-label d-block mb-2"><i class="bi bi-info-circle me-1"></i> Before you send</span>
                    <ul class="small text-muted ps-3 mb-0">
                        <li>E-mails go out with the CAFI logo and footer.</li>
                        <li><code>{name}</code>, <code>{business}</code>, <code>{reference}</code>, <code>{status}</code> and <code>{link}</code> are filled in for each person.</li>
                        <li>Inactive accounts are left out automatically, and nobody gets the same e-mail twice.</li>
                        <li>Use <strong>Preview</strong> to see it as the first recipient will.</li>
                        <li>Every e-mail sent is kept under <em>Recently sent</em>.</li>
                    </ul>
                </div>

                <livewire:compose-email.recent />
            </div>
        </div>

        {{-- Full-screen "All e-mails" drawer (opens on "open-email-history") --}}
        <livewire:compose-email.history />
    </div>
</x-app-layout>