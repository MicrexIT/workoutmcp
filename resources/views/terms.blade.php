<!doctype html>
<html lang="en" class="scroll-smooth">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Terms of Service · Workout Memory MCP</title>
    <meta name="description" content="Terms of service for Workout Memory, a remote MCP server for workout history.">
    <link rel="canonical" href="{{ route('terms') }}">
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
            <h1 class="mt-4 font-display text-[clamp(2.6rem,6vw,4.6rem)] uppercase leading-[0.95]">Terms of Service</h1>
            <p class="mt-6 text-lg leading-relaxed text-chalk-dim">
                These terms govern use of Workout Memory, operated by {{ $companyName }}. By creating an account, connecting an MCP client, or using the service, you agree to these terms.
            </p>

            <section class="mt-10 border-t border-chalk/10 pt-8">
                <h2 class="font-display text-2xl uppercase">Service</h2>
                <p class="mt-4 text-sm leading-relaxed text-chalk-dim">
                    Workout Memory stores workout history and training context so authorized AI clients can log, recall, correct, summarize, and share workouts at your request. The service is not medical care, coaching, emergency support, or a substitute for professional advice.
                </p>
            </section>

            <section class="mt-10 border-t border-chalk/10 pt-8">
                <h2 class="font-display text-2xl uppercase">Accounts and connectors</h2>
                <ul class="mt-4 flex flex-col gap-3 text-sm leading-relaxed text-chalk-dim">
                    <li>You are responsible for keeping your account credentials secure and for activity performed through authorized connectors.</li>
                    <li>You may disconnect an AI client or request account deletion at any time.</li>
                    <li>You must provide accurate information and use the service only for lawful personal or authorized business purposes.</li>
                </ul>
            </section>

            <section class="mt-10 border-t border-chalk/10 pt-8">
                <h2 class="font-display text-2xl uppercase">Your data</h2>
                <p class="mt-4 text-sm leading-relaxed text-chalk-dim">
                    You retain responsibility for the workout data, notes, goals, constraints, and other training context you submit. Workout Memory uses that data to operate the service as described in the Privacy Policy. Public workout share links are visible to anyone with the link until revoked.
                </p>
            </section>

            <section class="mt-10 border-t border-chalk/10 pt-8">
                <h2 class="font-display text-2xl uppercase">Acceptable use</h2>
                <ul class="mt-4 flex flex-col gap-3 text-sm leading-relaxed text-chalk-dim">
                    <li>Do not misuse the service, interfere with its operation, attempt unauthorized access, or bypass rate limits and security controls.</li>
                    <li>Do not submit illegal, harmful, abusive, or infringing content.</li>
                    <li>Do not rely on Workout Memory for medical diagnosis, injury treatment, or emergency decisions.</li>
                </ul>
            </section>

            <section class="mt-10 border-t border-chalk/10 pt-8">
                <h2 class="font-display text-2xl uppercase">Availability and changes</h2>
                <p class="mt-4 text-sm leading-relaxed text-chalk-dim">
                    The service is provided as available. Features may change, be suspended, or be discontinued. We may update these terms, and continued use after an update means you accept the updated terms.
                </p>
            </section>

            <section class="mt-10 border-t border-chalk/10 pt-8">
                <h2 class="font-display text-2xl uppercase">Liability</h2>
                <p class="mt-4 text-sm leading-relaxed text-chalk-dim">
                    To the maximum extent permitted by law, Workout Memory is provided without warranties and {{ $companyName }} is not liable for indirect, incidental, consequential, special, or punitive damages, or for losses caused by workouts, training decisions, third-party AI clients, outages, or data entered by users.
                </p>
            </section>

            <section class="mt-10 border-t border-chalk/10 pt-8">
                <h2 class="font-display text-2xl uppercase">Contact</h2>
                <p class="mt-4 text-sm leading-relaxed text-chalk-dim">
                    Questions about these terms can be sent to
                    <a href="mailto:{{ $supportEmail }}" class="text-chalk underline decoration-volt decoration-2 underline-offset-4 transition hover:text-volt">{{ $supportEmail }}</a>.
                </p>
            </section>
        </article>

        <footer class="mt-10 flex flex-wrap gap-4 border-t border-chalk/10 pt-8 font-mono text-xs text-chalk-dim">
            <a href="{{ route('docs') }}" class="transition hover:text-volt">Docs</a>
            <a href="{{ route('privacy') }}" class="transition hover:text-volt">Privacy</a>
            <a href="{{ route('support') }}" class="transition hover:text-volt">Support</a>
            <a href="{{ route('landing') }}" class="transition hover:text-volt">Home</a>
        </footer>
    </main>
</body>
</html>
