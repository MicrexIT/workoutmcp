@extends('debug.layout', ['title' => 'Workout Memory MCP'])

@section('content')
    <p class="font-mono text-xs uppercase tracking-[0.3em] text-volt">dashboard</p>
    <h1 class="mt-3 font-display text-4xl uppercase leading-none sm:text-5xl">Workout Memory MCP</h1>
    <p class="mt-4 max-w-2xl text-sm leading-relaxed text-chalk-dim">Your AI is the primary interface. These pages are a quick window into everything it has logged for you.</p>

    <section class="mt-8 grid gap-px border border-chalk/10 bg-chalk/10 sm:grid-cols-3">
        <div class="bg-ink p-6">
            <span class="font-mono text-[10px] uppercase tracking-[0.2em] text-chalk-dim">Seeded exercises</span>
            <strong class="mt-2 block font-display text-5xl text-volt">{{ $exerciseCount }}</strong>
        </div>
        <div class="bg-ink p-6">
            <span class="font-mono text-[10px] uppercase tracking-[0.2em] text-chalk-dim">Logged workouts</span>
            <strong class="mt-2 block font-display text-5xl text-volt">{{ $workoutCount }}</strong>
        </div>
        <div class="bg-ink p-6">
            <span class="font-mono text-[10px] uppercase tracking-[0.2em] text-chalk-dim">MCP endpoint</span>
            <code class="mt-3 block break-all font-mono text-sm text-chalk">/mcp/workout-memory</code>
        </div>
    </section>

    <section class="mt-6 border border-chalk/10 bg-ink-raised/60 p-6">
        <h2 class="font-display text-xl uppercase tracking-wide">Account</h2>
        <p class="mt-3 text-sm"><strong>{{ $user->name }}</strong> <span class="text-chalk-dim">{{ $user->email }}</span></p>
        @if ($user->profile?->goals)
            <p class="mt-2 max-w-2xl text-sm leading-relaxed text-chalk-dim">{{ $user->profile?->goals }}</p>
        @endif
    </section>

    <section class="mt-6 border border-chalk/10 bg-ink-raised/60 p-6">
        <div class="flex flex-col justify-between gap-4 sm:flex-row sm:items-center">
            <div>
                <h2 class="font-display text-xl uppercase tracking-wide">Need help?</h2>
                <p class="mt-3 max-w-2xl text-sm leading-relaxed text-chalk-dim">Send setup questions, account requests, workout corrections, or bug reports from the contact form.</p>
            </div>
            <a href="{{ route('support', ['from' => 'dashboard']) }}" class="inline-flex w-fit items-center justify-center border border-volt px-5 py-3 font-display text-sm uppercase tracking-wider text-volt transition hover:bg-volt hover:text-ink">Contact us</a>
        </div>
    </section>
@endsection
