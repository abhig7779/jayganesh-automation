@php
    // Error pages must render even when things are broken (no session, no Vite build, DB down),
    // so this layout uses no auth, no CSRF token and no database, and survives a missing Vite manifest.
    try {
        $assets = app(\Illuminate\Foundation\Vite::class)(['resources/css/app.css'])->toHtml();
    } catch (\Throwable) {
        $assets = '';
    }
    try {
        $home = route('dashboard');
    } catch (\Throwable) {
        $home = url('/');
    }
@endphp
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1, viewport-fit=cover">
    <meta name="theme-color" content="#111111">
    <meta name="robots" content="noindex, nofollow">
    <title>@yield('title') · Jay Ganesh CRM</title>
    <link rel="icon" href="{{ asset('img/icon-192.png') }}">
    {!! $assets !!}
    <style>
        /* Fallback if the CSS build is missing */
        body { margin: 0; background: #111; color: #111; font-family: system-ui, -apple-system, 'Segoe UI', Roboto, sans-serif; }
    </style>
</head>
<body class="min-h-dvh bg-ink font-sans text-ink antialiased">
<main class="flex min-h-dvh items-center justify-center px-4 py-10" style="min-height:100vh;display:flex;align-items:center;justify-content:center">
    <div class="w-full max-w-sm text-center" style="max-width:24rem;width:100%">
        <img src="{{ asset('img/logo.png') }}" alt="Jay Ganesh Car Accessories" class="mx-auto h-20 w-auto" style="height:5rem">
        <div class="mt-8 rounded-3xl bg-white p-6 shadow-xl" style="background:#fff;border-radius:1.5rem;padding:1.5rem;margin-top:2rem">
            <p class="text-xs font-semibold tracking-[0.3em] text-zinc-400">ERROR @yield('code')</p>
            <h1 class="mt-2 text-xl font-bold">@yield('heading')</h1>
            <p class="mt-2 text-sm text-zinc-600">@yield('message')</p>
            <div class="mt-6">
                @hasSection('action')
                    @yield('action')
                @else
                    <a href="{{ $home }}" class="btn-accent w-full">Go to home</a>
                @endif
            </div>
        </div>
    </div>
</main>
</body>
</html>
