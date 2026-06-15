<!doctype html>
<html lang="en" class="scroll-smooth">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Support · Workout Memory MCP</title>
    <meta name="description" content="Support and contact information for Workout Memory MCP.">
    <link rel="canonical" href="{{ route('support') }}">
    <link rel="icon" type="image/svg+xml" href="{{ asset('favicon.svg') }}">
    <meta name="theme-color" content="#191813">
    {{ \Illuminate\Support\Facades\Vite::fonts() }}
    @vite(['resources/css/app.css', 'resources/js/app.js'])
</head>
<body class="min-h-screen bg-ink font-sans text-chalk antialiased selection:bg-volt selection:text-ink">
    @include('partials.grain')

    <main class="mx-auto max-w-3xl px-5 py-12 sm:px-8 lg:py-16">
        <a href="{{ route('landing') }}" class="font-mono text-xs uppercase tracking-[0.24em] text-volt">Workout Memory</a>

        <header class="mt-8 border-b border-chalk/10 pb-8">
            <p class="font-mono text-xs uppercase tracking-[0.3em] text-chalk-dim">Help and contact</p>
            <h1 class="mt-4 font-display text-[clamp(2.6rem,6vw,4.6rem)] uppercase leading-[0.95]">Support</h1>
            <p class="mt-5 max-w-2xl text-lg leading-relaxed text-chalk-dim">
                Need help connecting Workout Memory, fixing a workout, deleting data, or reporting a security issue? Email support and include the AI client you use plus the MCP endpoint below.
            </p>
        </header>

        <section class="border-b border-chalk/10 py-8">
            <h2 class="font-display text-2xl uppercase">Contact</h2>
            <p class="mt-4 text-sm leading-relaxed text-chalk-dim">
                Email <a href="mailto:{{ $supportEmail }}" class="text-chalk underline decoration-volt decoration-2 underline-offset-4 transition hover:text-volt">{{ $supportEmail }}</a>.
            </p>
        </section>

        <section class="border-b border-chalk/10 py-8">
            <h2 class="font-display text-2xl uppercase">Connection details</h2>
            <dl class="mt-5 grid gap-4 text-sm sm:grid-cols-[12rem_1fr]">
                <dt class="font-mono text-chalk-dim">Product URL</dt>
                <dd><a href="{{ $publicUrl }}" class="break-all text-chalk underline decoration-volt decoration-2 underline-offset-4 transition hover:text-volt">{{ $publicUrl }}</a></dd>
                <dt class="font-mono text-chalk-dim">MCP endpoint</dt>
                <dd><code class="break-all border border-chalk/15 bg-ink-raised px-2 py-1 font-mono text-volt">{{ $mcpUrl }}</code></dd>
                <dt class="font-mono text-chalk-dim">Docs</dt>
                <dd><a href="{{ route('docs') }}" class="text-chalk underline decoration-volt decoration-2 underline-offset-4 transition hover:text-volt">{{ route('docs') }}</a></dd>
            </dl>
        </section>

        <section class="border-b border-chalk/10 py-8">
            <h2 class="font-display text-2xl uppercase">Before contacting support</h2>
            <ul class="mt-4 flex flex-col gap-3 text-sm leading-relaxed text-chalk-dim">
                <li>Confirm the MCP endpoint is exactly <code class="font-mono text-volt">{{ $mcpUrl }}</code>.</li>
                <li>Disconnect and reconnect the connector if OAuth authorization appears stale.</li>
                <li>If a workout is wrong, ask your AI client to fetch the workout, then update it in place instead of logging a duplicate.</li>
            </ul>
        </section>

        <footer class="flex flex-wrap gap-4 py-8 font-mono text-xs text-chalk-dim">
            <a href="{{ route('docs') }}" class="transition hover:text-volt">Docs</a>
            <a href="{{ route('privacy') }}" class="transition hover:text-volt">Privacy</a>
            <a href="{{ route('terms') }}" class="transition hover:text-volt">Terms</a>
            <a href="{{ route('landing') }}" class="transition hover:text-volt">Home</a>
        </footer>
    </main>
</body>
</html>
