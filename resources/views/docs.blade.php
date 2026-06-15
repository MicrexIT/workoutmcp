<!doctype html>
<html lang="en" class="scroll-smooth">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Documentation · Workout Memory MCP</title>
    <meta name="description" content="Connector documentation for Workout Memory, a remote MCP server for workout history.">
    <link rel="canonical" href="{{ route('docs') }}">
    <link rel="icon" type="image/svg+xml" href="{{ asset('favicon.svg') }}">
    <meta name="theme-color" content="#191813">
    {{ \Illuminate\Support\Facades\Vite::fonts() }}
    @vite(['resources/css/app.css', 'resources/js/app.js'])
</head>
<body class="min-h-screen bg-ink font-sans text-chalk antialiased selection:bg-volt selection:text-ink">
    @include('partials.grain')

    <main class="mx-auto max-w-4xl px-5 py-12 sm:px-8 lg:py-16">
        <a href="{{ route('landing') }}" class="font-mono text-xs uppercase tracking-[0.24em] text-volt">Workout Memory</a>

        <header class="mt-8 border-b border-chalk/10 pb-8">
            <p class="font-mono text-xs uppercase tracking-[0.3em] text-chalk-dim">Connector documentation</p>
            <h1 class="mt-4 font-display text-[clamp(2.6rem,6vw,4.6rem)] uppercase leading-[0.95]">Remote MCP server</h1>
            <p class="mt-5 max-w-2xl text-lg leading-relaxed text-chalk-dim">
                Workout Memory gives AI assistants a structured memory for workouts: lifting, yoga, spinning, mobility, conditioning, and more. Users sign in once, authorize the MCP connector, then log, recall, correct, and share workouts through natural language.
            </p>
        </header>

        <section class="border-b border-chalk/10 py-8">
            <h2 class="font-display text-2xl uppercase">Connection</h2>
            <dl class="mt-5 grid gap-4 text-sm sm:grid-cols-[12rem_1fr]">
                <dt class="font-mono text-chalk-dim">MCP endpoint</dt>
                <dd><code class="break-all border border-chalk/15 bg-ink-raised px-2 py-1 font-mono text-volt">{{ $mcpUrl }}</code></dd>
                <dt class="font-mono text-chalk-dim">Transport</dt>
                <dd>Streamable HTTP</dd>
                <dt class="font-mono text-chalk-dim">Authentication</dt>
                <dd>OAuth 2.1 with PKCE, dynamic client registration, refresh tokens, and <code class="font-mono text-volt">mcp:use</code> scope.</dd>
                <dt class="font-mono text-chalk-dim">Metadata</dt>
                <dd><code class="break-all font-mono text-volt">{{ $publicUrl }}/.well-known/oauth-authorization-server</code></dd>
            </dl>
        </section>

        <section class="border-b border-chalk/10 py-8">
            <h2 class="font-display text-2xl uppercase">Core capabilities</h2>
            <ul class="mt-5 grid gap-3 text-sm leading-relaxed text-chalk-dim sm:grid-cols-2">
                <li class="border border-chalk/10 bg-ink-raised/70 p-4">Log completed workouts from natural language.</li>
                <li class="border border-chalk/10 bg-ink-raised/70 p-4">Track live sessions with start, append, note, and finish tools.</li>
                <li class="border border-chalk/10 bg-ink-raised/70 p-4">Resolve exercise phrases and remember user corrections.</li>
                <li class="border border-chalk/10 bg-ink-raised/70 p-4">Return workout history, best efforts when relevant, and training summaries.</li>
                <li class="border border-chalk/10 bg-ink-raised/70 p-4">Store durable user context such as goals, constraints, and equipment.</li>
                <li class="border border-chalk/10 bg-ink-raised/70 p-4">Create revocable public workout share links only when explicitly requested.</li>
            </ul>
        </section>

        <section class="border-b border-chalk/10 py-8">
            <h2 class="font-display text-2xl uppercase">Setup</h2>
            <div class="mt-5 grid gap-6 md:grid-cols-2">
                <div>
                    <h3 class="font-display text-lg uppercase">ChatGPT</h3>
                    <ol class="mt-3 flex flex-col gap-2 text-sm leading-relaxed text-chalk-dim">
                        <li>Enable Developer mode in Settings -> Apps & Connectors -> Advanced settings.</li>
                        <li>Create a custom app named Workout Memory with the MCP endpoint above.</li>
                        <li>Select OAuth authentication, connect, sign in, and approve access.</li>
                    </ol>
                </div>
                <div>
                    <h3 class="font-display text-lg uppercase">Claude</h3>
                    <ol class="mt-3 flex flex-col gap-2 text-sm leading-relaxed text-chalk-dim">
                        <li>Open Settings -> Connectors -> Add custom connector.</li>
                        <li>Paste the MCP endpoint above.</li>
                        <li>Connect, sign in, and approve access.</li>
                    </ol>
                </div>
            </div>
        </section>

        <section class="border-b border-chalk/10 py-8">
            <h2 class="font-display text-2xl uppercase">Example prompts</h2>
            <ul class="mt-5 flex flex-col gap-3 font-mono text-sm text-chalk-dim">
                <li>"Log today's workout: bench press 5x5 at 80 kg, incline dumbbell press 3x10 at 26 kg."</li>
                <li>"Log 45 minutes of spinning and 20 minutes of yoga this morning."</li>
                <li>"What did I bench last week, and did I set any PRs?"</li>
                <li>"I meant front squats, not back squats. Fix the last workout and remember that wording."</li>
            </ul>
        </section>

        <footer class="flex flex-col gap-3 py-8 font-mono text-xs text-chalk-dim sm:flex-row sm:items-center sm:justify-between">
            <div class="flex flex-wrap gap-4">
                <a href="{{ route('privacy') }}" class="transition hover:text-volt">Privacy</a>
                <a href="{{ route('terms') }}" class="transition hover:text-volt">Terms</a>
                <a href="{{ route('support') }}" class="transition hover:text-volt">Support</a>
                <a href="{{ route('llms') }}" class="transition hover:text-volt">llms.txt</a>
            </div>
            <a href="mailto:{{ $supportEmail }}" class="transition hover:text-volt">{{ $supportEmail }}</a>
        </footer>
    </main>
</body>
</html>
