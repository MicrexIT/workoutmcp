@php
    $workoutName = $workout['name'] ?? 'Workout';
    $ogDescription = $lines->take(3)->map(fn (array $line): string => trim($line['name'].' '.$line['sets']))->implode(' · ');
@endphp
<!doctype html>
<html lang="en" class="scheme-dark">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>{{ $workoutName }} · Workout Memory</title>
    <meta name="robots" content="noindex, nofollow">
    <meta name="description" content="{{ $ogDescription }}">
    <link rel="icon" type="image/svg+xml" href="{{ asset('favicon.svg') }}">
    <meta name="theme-color" content="#191813">
    <meta property="og:type" content="website">
    <meta property="og:title" content="{{ $workoutName }} · {{ $workout['set_count'] }} sets">
    <meta property="og:description" content="{{ $ogDescription }}">
    <meta property="og:site_name" content="Workout Memory">
    <meta name="twitter:card" content="summary">
    {{ \Illuminate\Support\Facades\Vite::fonts() }}
    @vite(['resources/css/app.css', 'resources/js/app.js'])
</head>
<body class="min-h-screen bg-ink font-sans text-chalk antialiased selection:bg-volt selection:text-ink">
    @include('partials.grain')

    <main class="mx-auto flex min-h-screen w-full max-w-xl flex-col justify-center px-5 py-12">
        <a href="{{ route('landing') }}" class="mb-10 flex items-center justify-center gap-3">
            <svg viewBox="0 0 32 32" class="h-7 w-7" aria-hidden="true">
                <rect width="32" height="32" rx="6" class="fill-volt"/>
                <rect x="4" y="14" width="24" height="4" rx="2" class="fill-ink"/>
                <rect x="7" y="9" width="4" height="14" rx="1.5" class="fill-ink"/>
                <rect x="21" y="9" width="4" height="14" rx="1.5" class="fill-ink"/>
            </svg>
            <span class="font-display text-lg uppercase tracking-wide">Workout Memory</span>
        </a>

        <article class="-rotate-1 border border-chalk/15 bg-ink-raised/90 p-6 shadow-[12px_12px_0_rgba(216,244,59,0.14)] sm:p-8">
            <div class="flex items-center justify-between gap-4 font-mono text-[10px] uppercase tracking-[0.2em]">
                <span class="text-volt">Workout log</span>
                @if ($dateLabel)
                    <span class="text-chalk-dim">{{ $dateLabel }}</span>
                @endif
            </div>

            <h1 class="mt-4 font-display text-3xl uppercase leading-[0.95] sm:text-4xl">{{ $workoutName }}</h1>

            <p class="mt-3 font-mono text-xs text-chalk-dim">{{ collect([$workout['kind'], $workout['set_count'].' sets', $ownerFirstName ? 'by '.$ownerFirstName : null])->filter()->implode(' · ') }}</p>

            <div class="mt-6 flex flex-col border-t border-chalk/10">
                @foreach ($lines as $line)
                    <div class="flex items-baseline justify-between gap-6 border-b border-chalk/5 py-3">
                        <div>
                            <p class="text-sm font-medium">{{ $line['name'] }}</p>
                            @if ($line['variant'])
                                <p class="mt-0.5 font-mono text-[10px] uppercase tracking-[0.15em] text-chalk-dim">{{ $line['variant'] }}</p>
                            @endif
                        </div>
                        <p class="text-right font-mono text-xs text-chalk-dim">{{ $line['sets'] }}</p>
                    </div>
                @endforeach
            </div>
        </article>

        <p class="mt-12 text-center font-mono text-xs uppercase tracking-[0.25em] text-chalk-dim">Logged by talking to an AI</p>
        <p class="mx-auto mt-3 max-w-sm text-center text-sm leading-relaxed text-chalk-dim">
            Workout Memory gives ChatGPT, Claude and any AI with MCP support a permanent memory for training.
        </p>
        <div class="mt-6 flex justify-center">
            <a href="{{ route('landing') }}" class="bg-volt px-7 py-3.5 font-display text-sm uppercase tracking-wider text-ink transition hover:-translate-y-0.5 hover:bg-volt-hot">Get Workout Memory →</a>
        </div>
    </main>
</body>
</html>
