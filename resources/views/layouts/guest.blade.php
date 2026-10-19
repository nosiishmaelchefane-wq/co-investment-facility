{{-- Guest layout (Breeze pages: forgot / reset password …) — Bootstrap, CIF colours --}}
<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="csrf-token" content="{{ csrf_token() }}">

    <title>{{ $title ?? config('app.name', 'Laravel') }}</title>

    <link rel="icon" type="image/x-icon" href="{{ asset('favicon.ico') }}">
    <link rel="preconnect" href="https://fonts.bunny.net">
    <link href="https://fonts.bunny.net/css?family=figtree:400,500,600,700,800&display=swap" rel="stylesheet" />
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css" rel="stylesheet">

    @vite(['resources/css/app.css', 'resources/js/app.js'])
</head>
<body class="bg-light" style="font-family: 'Figtree', sans-serif;">
    <main class="min-vh-100 d-flex flex-column align-items-center justify-content-center py-5 px-3">
        <a href="/" class="mb-4 d-inline-block">
            <img src="{{ asset('images/logo.jpg') }}" alt="{{ config('app.name') }}" style="height: 64px; width: auto;">
        </a>

        <div class="card border-0 shadow-sm w-100" style="max-width: 440px; border-radius: 1rem;">
            <div class="card-body p-4 p-sm-5">
                {{ $slot }}
            </div>
        </div>

        <p class="text-muted small mt-4 mb-0">&copy; {{ date('Y') }} {{ config('app.name') }}</p>
    </main>
</body>
</html>