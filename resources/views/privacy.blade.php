<!doctype html>
<html lang="en" class="scroll-smooth">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Privacy Policy · Workout Memory MCP</title>
    <meta name="description" content="Privacy policy for Workout Memory, a remote MCP server for workout history.">
    <link rel="canonical" href="{{ route('privacy') }}">
    <link rel="icon" type="image/svg+xml" href="{{ asset('favicon.svg') }}">
    <meta name="theme-color" content="#191813">
    {{ \Illuminate\Support\Facades\Vite::fonts() }}
    @vite(['resources/css/app.css', 'resources/js/app.js'])
</head>
<body class="min-h-screen bg-ink font-sans text-chalk antialiased selection:bg-volt selection:text-ink">
    @include('partials.grain')

    <main class="mx-auto max-w-3xl px-5 py-12 sm:px-8 lg:py-16">
        <a href="{{ route('landing') }}" class="font-mono text-xs uppercase tracking-[0.24em] text-volt">Workout Memory</a>

        <article class="mt-8">
            <p class="font-mono text-xs uppercase tracking-[0.3em] text-chalk-dim">Last updated June 15, 2026</p>
            <h1 class="mt-4 font-display text-[clamp(2.6rem,6vw,4.6rem)] uppercase leading-[0.95]">Privacy Policy</h1>
            <p class="mt-6 text-lg leading-relaxed text-chalk-dim">
                {{ $companyName }} operates Workout Memory. This policy explains what data Workout Memory collects, why it is used, how long it is kept, and how to contact us.
            </p>

            <section class="mt-10 border-t border-chalk/10 pt-8">
                <h2 class="font-display text-2xl uppercase">Data we collect</h2>
                <ul class="mt-4 flex flex-col gap-3 text-sm leading-relaxed text-chalk-dim">
                    <li>Account data: name, email address, password hash, and sign-in session data.</li>
                    <li>Training data: workouts, exercises, sets, reps, loads, durations, distances, notes, goals, constraints, equipment, preferred units, timezone, and user-provided training context.</li>
                    <li>Connector data: OAuth client registrations, authorization approvals, access tokens, refresh tokens, and the single <code class="font-mono text-volt">mcp:use</code> scope needed to operate the MCP connector.</li>
                    <li>Exercise resolution data: phrase memories and short-lived resolution evidence used to match phrases like "DLs" to the intended exercise.</li>
                    <li>Share data: public workout share links and the workout details shown on those links, only when a user explicitly asks to create a share link.</li>
                    <li>Operational data: standard server and security logs such as request path, timestamp, IP address, user agent, and error details when needed to keep the service reliable and secure.</li>
                </ul>
            </section>

            <section class="mt-10 border-t border-chalk/10 pt-8">
                <h2 class="font-display text-2xl uppercase">How we use data</h2>
                <p class="mt-4 text-sm leading-relaxed text-chalk-dim">
                    Workout Memory uses data to authenticate users, authorize MCP clients, log and correct workouts, resolve exercise names, answer training-history questions, show the web dashboard, create requested share links, prevent abuse, debug errors, and maintain the service. We do not sell personal data or serve advertising.
                </p>
            </section>

            <section class="mt-10 border-t border-chalk/10 pt-8">
                <h2 class="font-display text-2xl uppercase">Sharing and processors</h2>
                <p class="mt-4 text-sm leading-relaxed text-chalk-dim">
                    Your authorized AI client receives only the tool inputs and tool responses needed for the request you make. Public workout share links are visible to anyone with the link until revoked. The service is hosted on infrastructure providers, including Hetzner for application hosting and Cloudflare for DNS, TLS proxying, and edge protection. We may disclose data if required by law or to protect the service and its users.
                </p>
            </section>

            <section class="mt-10 border-t border-chalk/10 pt-8">
                <h2 class="font-display text-2xl uppercase">Retention and control</h2>
                <ul class="mt-4 flex flex-col gap-3 text-sm leading-relaxed text-chalk-dim">
                    <li>Workout, exercise, profile, and account data are kept until deleted or until you ask us to delete your account.</li>
                    <li>Access tokens are short-lived, refresh tokens expire, and connector approvals can be revoked by disconnecting the connector in the AI client.</li>
                    <li>Workout share links can be revoked from the web dashboard; deleted workouts also disable their share links.</li>
                    <li>Operational logs are kept only as long as needed for security, reliability, debugging, or legal obligations.</li>
                </ul>
            </section>

            <section class="mt-10 border-t border-chalk/10 pt-8">
                <h2 class="font-display text-2xl uppercase">Contact</h2>
                <p class="mt-4 text-sm leading-relaxed text-chalk-dim">
                    For access, correction, deletion, export, privacy, or security requests, email
                    <a href="mailto:{{ $supportEmail }}" class="text-chalk underline decoration-volt decoration-2 underline-offset-4 transition hover:text-volt">{{ $supportEmail }}</a>.
                </p>
            </section>
        </article>

        <footer class="mt-10 flex flex-wrap gap-4 border-t border-chalk/10 pt-8 font-mono text-xs text-chalk-dim">
            <a href="{{ route('docs') }}" class="transition hover:text-volt">Docs</a>
            <a href="{{ route('terms') }}" class="transition hover:text-volt">Terms</a>
            <a href="{{ route('support') }}" class="transition hover:text-volt">Support</a>
            <a href="{{ route('landing') }}" class="transition hover:text-volt">Home</a>
        </footer>
    </main>
</body>
</html>
