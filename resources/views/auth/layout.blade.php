<!doctype html>
<html lang="en" class="scheme-dark">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>@yield('title') · Workout Memory MCP</title>
    <link rel="icon" type="image/svg+xml" href="{{ asset('favicon.svg') }}">
    <meta name="theme-color" content="#191813">
    {{ \Illuminate\Support\Facades\Vite::fonts() }}
    @vite(['resources/css/app.css', 'resources/js/app.js'])
</head>
<body class="min-h-screen bg-ink font-sans text-chalk antialiased selection:bg-volt selection:text-ink">
    @include('partials.grain')

    <main class="mx-auto flex min-h-screen w-full max-w-md flex-col justify-center px-5 py-12">
        <a href="{{ route('landing') }}" class="mb-8 flex items-center justify-center gap-3">
            <svg viewBox="0 0 32 32" class="h-8 w-8" aria-hidden="true">
                <rect width="32" height="32" rx="6" class="fill-volt"/>
                <rect x="4" y="14" width="24" height="4" rx="2" class="fill-ink"/>
                <rect x="7" y="9" width="4" height="14" rx="1.5" class="fill-ink"/>
                <rect x="21" y="9" width="4" height="14" rx="1.5" class="fill-ink"/>
            </svg>
            <span class="font-display text-xl uppercase tracking-wide">Workout Memory</span>
        </a>

        @if (session('status'))
            <div class="mb-5 border border-volt/40 bg-volt/10 px-4 py-3 font-mono text-sm">{{ session('status') }}</div>
        @endif

        <section class="border border-chalk/12 bg-ink-raised/80 p-7 sm:p-8">
            @yield('card')
        </section>

        @hasSection('footer')
            <p class="mt-6 text-center font-mono text-xs text-chalk-dim">@yield('footer')</p>
        @endif
    </main>
</body>
</html>
