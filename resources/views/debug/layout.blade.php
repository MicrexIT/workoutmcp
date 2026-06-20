<!doctype html>
<html lang="en" class="scheme-dark">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>{{ $title ?? 'Workout Memory MCP' }}</title>
    <link rel="icon" type="image/svg+xml" href="{{ asset('favicon.svg') }}">
    <meta name="theme-color" content="#191813">
    {{ \Illuminate\Support\Facades\Vite::fonts() }}
    @vite(['resources/css/app.css', 'resources/js/app.js'])
</head>
<body class="min-h-screen bg-ink font-sans text-chalk antialiased selection:bg-volt selection:text-ink">
    @include('partials.grain')

    <header class="sticky top-0 z-50 border-b border-chalk/10 bg-ink/90 backdrop-blur">
        <nav class="mx-auto flex max-w-6xl flex-wrap items-center justify-between gap-x-6 gap-y-3 px-5 py-4 sm:px-8">
            <a href="{{ route('home') }}" class="flex items-center gap-3">
                <svg viewBox="0 0 32 32" class="h-7 w-7" aria-hidden="true">
                    <rect width="32" height="32" rx="6" class="fill-volt"/>
                    <rect x="4" y="14" width="24" height="4" rx="2" class="fill-ink"/>
                    <rect x="7" y="9" width="4" height="14" rx="1.5" class="fill-ink"/>
                    <rect x="21" y="9" width="4" height="14" rx="1.5" class="fill-ink"/>
                </svg>
                <span class="font-display text-lg uppercase tracking-wide">Workout Memory MCP</span>
            </a>
            <div class="flex flex-wrap items-center gap-x-5 gap-y-2 font-mono text-xs uppercase tracking-[0.18em] text-chalk-dim">
                <a href="{{ route('exercises.index') }}" class="transition hover:text-volt">Exercises</a>
                <a href="{{ route('workouts.index') }}" class="transition hover:text-volt">Workouts</a>
                <a href="{{ route('support', ['from' => 'app']) }}" class="transition hover:text-volt">Need help?</a>
                <span class="hidden normal-case tracking-normal sm:inline">{{ auth()->user()->email }}</span>
                <form method="POST" action="{{ route('logout') }}" class="inline">
                    @csrf
                    <button type="submit" class="cursor-pointer uppercase tracking-[0.18em] transition hover:text-volt">Sign out</button>
                </form>
            </div>
        </nav>
    </header>

    <main class="mx-auto max-w-6xl px-5 py-10 sm:px-8">
        @if (session('status'))
            <div class="mb-6 border border-volt/40 bg-volt/10 px-4 py-3 font-mono text-sm">{{ session('status') }}</div>
        @endif
        @if (session('error'))
            <div class="mb-6 border border-red-400/40 bg-red-400/10 px-4 py-3 font-mono text-sm text-red-200">{{ session('error') }}</div>
        @endif
        @yield('content')
    </main>
</body>
</html>
