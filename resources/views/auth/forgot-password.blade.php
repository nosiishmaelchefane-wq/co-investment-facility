<x-guest-layout>
    <div class="text-center mb-3"><i class="bi bi-key text-cif-primary" style="font-size: 2.5rem;"></i></div>
    <h1 class="h4 fw-bold text-cif-navy text-center mb-2">Forgot your password?</h1>
    <p class="text-secondary small text-center mb-4">Enter the e-mail address you registered with and we'll send you a link to choose a new password.</p>

    @if (session('status'))
        <div class="alert alert-success small py-2"><i class="bi bi-check-circle me-1"></i> {{ session('status') }}</div>
    @endif

    <form method="POST" action="{{ route('password.email') }}" novalidate>
        @csrf

        <div class="mb-4">
            <label for="email" class="form-label">Email address</label>
            <div class="input-group">
                <span class="input-group-text"><i class="bi bi-envelope"></i></span>
                <input id="email" type="email" name="email" value="{{ old('email') }}" required autofocus autocomplete="username"
                       placeholder="you@company.co.ls" class="form-control @error('email') is-invalid @enderror">
            </div>
            @error('email') <div class="text-danger small mt-1">{{ $message }}</div> @enderror
        </div>

        <button type="submit" class="btn btn-cif-primary btn-lg w-100">
            Email password reset link <i class="bi bi-send ms-1"></i>
        </button>
    </form>

    <p class="text-center small mt-4 mb-0">
        <a href="{{ url('/') }}" class="fw-semibold text-cif-primary text-decoration-none"><i class="bi bi-arrow-left me-1"></i> Back to sign in</a>
    </p>
</x-guest-layout>