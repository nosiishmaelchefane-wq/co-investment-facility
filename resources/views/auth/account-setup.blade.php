<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>{{ $isReset ? 'Reset password' : 'Set up your account' }} · {{ config('app.name', 'Co-Investment Facility') }}</title>

    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css" rel="stylesheet">
    @vite(['resources/css/app.css'])
</head>
<body class="bg-soft">
    <main class="min-vh-100 d-flex align-items-center justify-content-center p-3">
        <div class="card card-modern w-100" style="max-width: 460px;">
            <div class="drawer-header rounded-top">
                <div>
                    <div class="drawer-eyebrow">LEHSFF Co-Investment Facility</div>
                    <h5 class="mb-0">{{ $isReset ? 'Choose a new password' : 'Set up your account' }}</h5>
                </div>
            </div>

            <div class="card-body p-4">
                <div class="d-flex align-items-center gap-3 mb-4">
                    <span class="avatar">{{ $user->initials }}</span>
                    <div class="overflow-hidden">
                        <div class="fw-semibold text-navy text-truncate">{{ $user->name }}</div>
                        <div class="small text-muted text-truncate">{{ $user->email }}</div>
                    </div>
                </div>

                <form method="POST" action="{{ $action }}" class="form-modern" novalidate>
                    @csrf

                    <div class="mb-3">
                        <label class="form-label" for="password">New password <span class="req">*</span></label>
                        <input type="password" id="password" name="password" autocomplete="new-password" autofocus
                               class="form-control @error('password') is-invalid @enderror">
                        @error('password') <div class="invalid-feedback">{{ $message }}</div> @enderror
                        <div class="form-text">At least 8 characters.</div>
                    </div>

                    <div class="mb-4">
                        <label class="form-label" for="password_confirmation">Confirm password <span class="req">*</span></label>
                        <input type="password" id="password_confirmation" name="password_confirmation" autocomplete="new-password" class="form-control">
                    </div>

                    <button type="submit" class="btn btn-brand w-100">
                        <i class="bi bi-shield-check me-1"></i> {{ $isReset ? 'Save new password' : 'Activate my account' }}
                    </button>
                </form>
            </div>
        </div>
    </main>
</body>
</html>