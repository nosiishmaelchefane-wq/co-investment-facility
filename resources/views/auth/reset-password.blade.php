<x-guest-layout>
    @php $isInvite = $request->boolean('invite'); @endphp
    <div class="text-center mb-3"><i class="bi {{ $isInvite ? 'bi-person-check' : 'bi-shield-lock' }} text-cif-primary" style="font-size: 2.5rem;"></i></div>
    <h1 class="h4 fw-bold text-cif-navy text-center mb-2">{{ $isInvite ? 'Set up your account' : 'Choose a new password' }}</h1>
    <p class="text-secondary small text-center mb-4">{{ $isInvite ? 'Welcome! Choose a password (at least 8 characters) to activate your account.' : "Use at least 8 characters. You'll sign in with it next time." }}</p>

    <form method="POST" action="{{ route('password.store') }}" novalidate>
        @csrf
        <input type="hidden" name="token" value="{{ $request->route('token') }}">

        <div class="mb-3">
            <label for="email" class="form-label">Email address</label>
            <div class="input-group">
                <span class="input-group-text"><i class="bi bi-envelope"></i></span>
                <input id="email" type="email" name="email" value="{{ old('email', $request->email) }}" required autocomplete="username"
                       class="form-control @error('email') is-invalid @enderror">
            </div>
            @error('email') <div class="text-danger small mt-1">{{ $message }}</div> @enderror
        </div>

        <div class="mb-3">
            <label for="password" class="form-label">{{ $isInvite ? 'Password' : 'New password' }}</label>
            <div class="input-group">
                <span class="input-group-text"><i class="bi bi-lock"></i></span>
                <input id="password" type="password" name="password" required autofocus autocomplete="new-password"
                       placeholder="Min. 8 characters" class="form-control @error('password') is-invalid @enderror">
            </div>
            @error('password') <div class="text-danger small mt-1">{{ $message }}</div> @enderror
        </div>

        <div class="mb-4">
            <label for="password_confirmation" class="form-label">{{ $isInvite ? 'Confirm password' : 'Confirm new password' }}</label>
            <div class="input-group">
                <span class="input-group-text"><i class="bi bi-lock"></i></span>
                <input id="password_confirmation" type="password" name="password_confirmation" required autocomplete="new-password"
                       placeholder="Repeat password" class="form-control @error('password_confirmation') is-invalid @enderror">
            </div>
            @error('password_confirmation') <div class="text-danger small mt-1">{{ $message }}</div> @enderror
        </div>

        <button type="submit" class="btn btn-cif-primary btn-lg w-100">
            {{ $isInvite ? 'Activate my account' : 'Reset password' }} <i class="bi bi-check-lg ms-1"></i>
        </button>
    </form>

    <p class="text-center small mt-4 mb-0">
        <a href="{{ url('/') }}" class="fw-semibold text-cif-primary text-decoration-none"><i class="bi bi-arrow-left me-1"></i> Back to sign in</a>
    </p>
</x-guest-layout>