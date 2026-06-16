<!doctype html>
<html lang="en" class="scroll-smooth">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Workout Memory · the workout app you never open</title>
    <meta name="description" content="Workout Memory is an MCP server that gives ChatGPT, Claude and any AI with MCP support a permanent memory for your training. Say what you did and it logs sets, minutes, distance, notes, and answers months later.">
    <link rel="canonical" href="{{ url('/') }}">
    <link rel="icon" type="image/svg+xml" href="{{ asset('favicon.svg') }}">
    <meta name="theme-color" content="#191813">
    <meta property="og:type" content="website">
    <meta property="og:url" content="{{ url('/') }}">
    <meta property="og:title" content="Workout Memory · the workout app you never open">
    <meta property="og:description" content="Give ChatGPT, Claude and any AI with MCP support a permanent memory for your training. Say what you did and each workout becomes history your AI can answer from.">
    <meta name="twitter:card" content="summary">
    {{ \Illuminate\Support\Facades\Vite::fonts() }}
    @vite(['resources/css/app.css', 'resources/js/app.js'])
</head>
<body class="min-h-screen bg-ink font-sans text-chalk antialiased selection:bg-volt selection:text-ink">
    @include('partials.grain')

    <header class="sticky top-0 z-50 border-b border-chalk/10 bg-ink/90 backdrop-blur">
        <nav class="mx-auto flex max-w-6xl items-center justify-between gap-6 px-5 py-4 sm:px-8">
            <a href="{{ route('landing') }}" class="flex items-center gap-3">
                <svg viewBox="0 0 32 32" class="h-7 w-7" aria-hidden="true">
                    <rect width="32" height="32" rx="6" class="fill-volt"/>
                    <rect x="4" y="14" width="24" height="4" rx="2" class="fill-ink"/>
                    <rect x="7" y="9" width="4" height="14" rx="1.5" class="fill-ink"/>
                    <rect x="21" y="9" width="4" height="14" rx="1.5" class="fill-ink"/>
                </svg>
                <span class="font-display text-lg uppercase tracking-wide">Workout Memory</span>
            </a>
            <div class="flex items-center gap-6">
                <div class="hidden items-center gap-6 font-mono text-xs uppercase tracking-[0.18em] text-chalk-dim md:flex">
                    <a href="#how" class="transition hover:text-volt">How it works</a>
                    <a href="#setup" class="transition hover:text-volt">Set up</a>
                    <a href="#faq" class="transition hover:text-volt">FAQ</a>
                </div>
                @auth
                    <a href="{{ route('home') }}" class="border border-volt px-4 py-2 font-display text-sm uppercase tracking-wider text-volt transition hover:bg-volt hover:text-ink">Dashboard</a>
                @else
                    <a href="{{ route('login') }}" class="border border-chalk/25 px-4 py-2 font-display text-sm uppercase tracking-wider transition hover:border-volt hover:text-volt">Sign in</a>
                @endauth
            </div>
        </nav>
    </header>

    <main>
        {{-- ============ HERO ============ --}}
        <section class="relative overflow-hidden">
            <div aria-hidden="true" class="absolute inset-0 bg-[radial-gradient(58%_48%_at_50%_0%,rgba(216,244,59,0.10),transparent_70%)]"></div>
            <div class="relative mx-auto grid max-w-6xl items-center gap-14 px-5 py-16 sm:px-8 lg:grid-cols-[1.05fr_0.95fr] lg:py-24">
                <div>
                    <p class="font-mono text-xs uppercase tracking-[0.28em] text-volt">An MCP server for ChatGPT, Claude + any AI that speaks MCP</p>
                    <h1 class="mt-5 font-display text-[clamp(2.9rem,6vw,5.2rem)] uppercase leading-[0.94]">
                        The workout app<br>you <span class="text-volt">never open</span>.
                    </h1>
                    <p class="mt-6 max-w-xl text-lg leading-relaxed text-chalk-dim">
                        Workout Memory gives ChatGPT, Claude and any AI with MCP support a permanent
                        memory for your training. Say what you did, in your own words, at the gym,
                        on the bike, on the mat, or hours later. Each workout becomes history your AI can actually answer from.
                    </p>
                    <div class="mt-8 flex flex-wrap items-center gap-x-5 gap-y-4">
                        <a href="#setup" data-tab="tab-chatgpt" class="bg-volt px-7 py-4 font-display text-base uppercase tracking-wider text-ink transition hover:-translate-y-0.5 hover:bg-volt-hot">Set up in ChatGPT</a>
                        <a href="#setup" data-tab="tab-claude" class="border border-chalk/25 px-7 py-4 font-display text-base uppercase tracking-wider transition hover:-translate-y-0.5 hover:border-volt hover:text-volt">Set up in Claude</a>
                        <a href="#setup" data-tab="tab-other" class="font-mono text-xs text-chalk-dim underline decoration-volt decoration-2 underline-offset-4 transition hover:text-volt">another AI? it works too →</a>
                    </div>
                    <p class="mt-5 font-mono text-xs text-chalk-dim">// free in early access · one URL · setup ≈ 2 minutes</p>
                </div>

                {{-- Chat artifact --}}
                <div class="relative">
                    <div class="-rotate-1 border border-chalk/15 bg-ink-raised/90 p-5 shadow-[12px_12px_0_rgba(216,244,59,0.14)] sm:p-6">
                        <div class="flex items-center justify-between border-b border-chalk/10 pb-3 font-mono text-[10px] uppercase tracking-[0.2em] text-chalk-dim">
                            <span>your ai · any chat</span>
                            <span class="flex items-center gap-1.5"><span class="inline-block h-1.5 w-1.5 rounded-full bg-volt"></span> workout-memory connected</span>
                        </div>
                        <div class="mt-4 flex flex-col gap-3.5">
                            <div class="max-w-[88%] self-end rounded-lg rounded-br-none border border-volt/30 bg-volt/15 px-4 py-3 text-sm leading-relaxed animate-rise [animation-delay:200ms] motion-reduce:animate-none">
                                Done at the gym: bench 5×5 at 80 kg, incline DBs 3×10 at 26, dips 12/10/8.
                            </div>
                            <div class="max-w-[92%] self-start rounded-lg rounded-bl-none border border-chalk/12 bg-ink px-4 py-3.5 animate-rise [animation-delay:1000ms] motion-reduce:animate-none">
                                <p class="font-mono text-[10px] uppercase tracking-[0.2em] text-volt">Logged · push day · today</p>
                                <div class="mt-3 flex flex-col gap-2 font-mono text-xs">
                                    <div class="flex items-center justify-between gap-3">
                                        <span>Bench Press</span>
                                        <span class="flex items-center gap-2 text-chalk-dim">5×5 · 80 kg <span class="rounded-sm bg-volt px-1.5 py-0.5 text-[9px] font-semibold text-ink">PB +2.5 KG</span></span>
                                    </div>
                                    <div class="flex items-center justify-between gap-3">
                                        <span>Incline DB Press</span>
                                        <span class="text-chalk-dim">3×10 · 26 kg</span>
                                    </div>
                                    <div class="flex items-center justify-between gap-3">
                                        <span>Dips</span>
                                        <span class="text-chalk-dim">12 / 10 / 8 · BW</span>
                                    </div>
                                </div>
                                <p class="mt-3 border-t border-chalk/10 pt-3 text-xs leading-relaxed text-chalk-dim">
                                    New bench 5×5 best, up 2.5 kg since 28 May.
                                </p>
                            </div>
                            <div class="max-w-[88%] self-end rounded-lg rounded-br-none border border-volt/30 bg-volt/15 px-4 py-3 text-sm leading-relaxed animate-rise [animation-delay:1800ms] motion-reduce:animate-none">
                                how's my squat trending this month?
                            </div>
                            <div class="max-w-[92%] self-start rounded-lg rounded-bl-none border border-chalk/12 bg-ink px-4 py-3 text-sm leading-relaxed animate-rise [animation-delay:2600ms] motion-reduce:animate-none">
                                Four sessions in June: top set moved 100 → 107.5 kg. 110 looks ready for Friday.<span class="ml-1 text-volt animate-blink motion-reduce:animate-none">▍</span>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </section>

        {{-- ============ MARQUEE ============ --}}
        <div class="overflow-hidden border-y border-chalk/10 py-4" aria-hidden="true">
            <div class="flex w-max animate-marquee motion-reduce:animate-none">
                @foreach ([0, 1] as $half)
                    <div class="flex shrink-0 items-center">
                        @foreach ([0, 1] as $repeat)
                            @foreach (["Say it, it's logged", 'No app to open', 'Every set remembered', 'Ask months later'] as $phrase)
                                <span class="whitespace-nowrap px-6 font-display text-2xl uppercase text-transparent [-webkit-text-stroke:1.2px_rgba(241,239,230,0.32)] sm:text-3xl">{{ $phrase }}</span>
                                <span class="text-volt">✦</span>
                            @endforeach
                        @endforeach
                    </div>
                @endforeach
            </div>
        </div>

        {{-- ============ 01 · THE IDEA ============ --}}
        <section class="scroll-mt-24">
            <div class="mx-auto max-w-6xl px-5 py-20 sm:px-8 lg:py-24">
                <p class="font-mono text-xs uppercase tracking-[0.3em] text-volt">01 / the idea</p>
                <h2 class="mt-4 max-w-3xl font-display text-[clamp(2rem,4.5vw,3.4rem)] uppercase leading-[0.95]">
                    Logging was an app.<br>Now it's a sentence.
                </h2>
                <div class="mt-8 grid gap-8 lg:grid-cols-2">
                    <p class="text-lg leading-relaxed text-chalk-dim">
                        Apps like <a href="https://www.strongapp.io" target="_blank" rel="noopener" class="text-chalk underline decoration-volt decoration-2 underline-offset-4 transition hover:text-volt">Strong</a>
                        perfected detailed workout logs. Workout Memory keeps the log and drops the app:
                        lifting, yoga, spinning, mobility and conditioning all become part of the same history.
                        Say it, it's saved. Ask, and it remembers.
                    </p>
                    <p class="text-lg leading-relaxed text-chalk-dim">
                        Under the hood it's an MCP server — the open standard for giving AI apps tools and memory.
                        To you it just means your assistant gains 18 precise tools for logging, recalling and
                        correcting workouts, behind one sign-in that you control.
                    </p>
                </div>
            </div>
        </section>

        {{-- ============ 02 · HOW IT WORKS ============ --}}
        <section id="how" class="scroll-mt-24 border-t border-chalk/10">
            <div class="mx-auto max-w-6xl px-5 py-20 sm:px-8 lg:py-24">
                <p class="font-mono text-xs uppercase tracking-[0.3em] text-volt">02 / how it works</p>
                <h2 class="mt-4 font-display text-[clamp(2rem,4.5vw,3.4rem)] uppercase leading-[0.95]">Three moves. One is talking.</h2>
                <div class="mt-10 grid gap-px border border-chalk/10 bg-chalk/10 md:grid-cols-3">
                    <div class="bg-ink p-8">
                        <span class="font-display text-5xl text-volt">1</span>
                        <h3 class="mt-4 font-display text-xl uppercase tracking-wide">Connect once</h3>
                        <p class="mt-3 text-sm leading-relaxed text-chalk-dim">
                            Add it to ChatGPT, Claude or any MCP client as a custom connector: paste one URL,
                            sign in, approve. Two minutes, once, on phone or desktop.
                        </p>
                    </div>
                    <div class="bg-ink p-8">
                        <span class="font-display text-5xl text-volt">2</span>
                        <h3 class="mt-4 font-display text-xl uppercase tracking-wide">Talk like you train</h3>
                        <p class="mt-3 text-sm leading-relaxed text-chalk-dim">
                            <span class="font-mono text-chalk">“squats 5×5 at 100, then RDLs.”</span> Voice or text,
                            during the workout or after. It resolves your phrasing into real exercises and flags anything it assumed.
                        </p>
                    </div>
                    <div class="bg-ink p-8">
                        <span class="font-display text-5xl text-volt">3</span>
                        <h3 class="mt-4 font-display text-xl uppercase tracking-wide">Ask like it was there</h3>
                        <p class="mt-3 text-sm leading-relaxed text-chalk-dim">
                            <span class="font-mono text-chalk">“when did I last train legs?”</span>
                            <span class="font-mono text-chalk">“bench PR?”</span>
                            <span class="font-mono text-chalk">“plan Friday around my numbers.”</span>
                            Answers come from your actual history, not vibes.
                        </p>
                    </div>
                </div>
            </div>
        </section>

        {{-- ============ 03 · WHAT IT REMEMBERS ============ --}}
        <section class="scroll-mt-24 border-t border-chalk/10">
            <div class="mx-auto max-w-6xl px-5 py-20 sm:px-8 lg:py-24">
                <p class="font-mono text-xs uppercase tracking-[0.3em] text-volt">03 / what it remembers</p>
                <h2 class="mt-4 font-display text-[clamp(2rem,4.5vw,3.4rem)] uppercase leading-[0.95]">A memory built for training.</h2>
                <div class="mt-10 grid gap-px border border-chalk/10 bg-chalk/10 sm:grid-cols-2 lg:grid-cols-3">
                    <div class="bg-ink p-7">
                        <p class="font-mono text-xs text-volt">live_sessions</p>
                        <p class="mt-3 text-sm leading-relaxed text-chalk-dim">Start a session at the gym and append sets as they happen. Forget to close it? It finishes itself after 18 hours.</p>
                    </div>
                    <div class="bg-ink p-7">
                        <p class="font-mono text-xs text-volt">sets_minutes_distance</p>
                        <p class="mt-3 text-sm leading-relaxed text-chalk-dim">Sets, reps, loads, minutes, distance and notes. Structured history, not a text blob lost in a chat thread.</p>
                    </div>
                    <div class="bg-ink p-7">
                        <p class="font-mono text-xs text-volt">best_sets_and_history</p>
                        <p class="mt-3 text-sm leading-relaxed text-chalk-dim">Per-exercise history with best efforts when relevant, so a PR gets called out the moment you beat it.</p>
                    </div>
                    <div class="bg-ink p-7">
                        <p class="font-mono text-xs text-volt">your_vocabulary</p>
                        <p class="mt-3 text-sm leading-relaxed text-chalk-dim">“DLs”, “that cable thing”. It learns what your words mean, and remembers corrections for next time.</p>
                    </div>
                    <div class="bg-ink p-7">
                        <p class="font-mono text-xs text-volt">training_context</p>
                        <p class="mt-3 text-sm leading-relaxed text-chalk-dim">Goals, injuries and constraints, the equipment you actually have, so plans fit your reality.</p>
                    </div>
                    <div class="bg-ink p-7">
                        <p class="font-mono text-xs text-volt">full_control</p>
                        <p class="mt-3 text-sm leading-relaxed text-chalk-dim">Edit, merge or delete workouts by saying so, and browse your whole log on the web after <a href="{{ route('login') }}" class="text-chalk underline decoration-volt decoration-2 underline-offset-4 transition hover:text-volt">signing in</a>.</p>
                    </div>
                </div>
            </div>
        </section>

        {{-- ============ 04 · SET UP ============ --}}
        <section id="setup" class="scroll-mt-24 border-t border-chalk/10">
            <div class="mx-auto max-w-6xl px-5 py-20 sm:px-8 lg:py-24">
                <p class="font-mono text-xs uppercase tracking-[0.3em] text-volt">04 / set up</p>
                <h2 class="mt-4 max-w-3xl font-display text-[clamp(2rem,4.5vw,3.4rem)] uppercase leading-[0.95]">Two minutes to a memory that never misses a set.</h2>

                @if ($registrationOpen)
                    <p class="mt-6 max-w-2xl border border-volt/40 bg-volt/5 px-4 py-3 font-mono text-sm text-chalk">
                        Step zero: <a href="{{ route('register') }}" class="text-volt underline underline-offset-4">create your free account</a>.
                        You'll approve the connection with it.
                    </p>
                @else
                    <p class="mt-6 max-w-2xl border border-chalk/15 bg-ink-raised px-4 py-3 font-mono text-sm text-chalk-dim">
                        Registration is closed at the moment. Existing accounts can
                        <a href="{{ route('login') }}" class="text-volt underline underline-offset-4">sign in</a> as usual.
                    </p>
                @endif

                <div class="mt-10 flex flex-wrap gap-2">
                    <input id="tab-chatgpt" name="setup-tab" type="radio" class="peer/cg sr-only" checked>
                    <input id="tab-claude" name="setup-tab" type="radio" class="peer/cl sr-only">
                    <input id="tab-other" name="setup-tab" type="radio" class="peer/oc sr-only">

                    <label for="tab-chatgpt" class="cursor-pointer select-none border border-chalk/20 px-5 py-2.5 font-display text-sm uppercase tracking-wider text-chalk-dim transition hover:border-chalk/50 hover:text-chalk peer-checked/cg:border-volt peer-checked/cg:bg-volt peer-checked/cg:text-ink peer-focus-visible/cg:ring-2 peer-focus-visible/cg:ring-volt">ChatGPT</label>
                    <label for="tab-claude" class="cursor-pointer select-none border border-chalk/20 px-5 py-2.5 font-display text-sm uppercase tracking-wider text-chalk-dim transition hover:border-chalk/50 hover:text-chalk peer-checked/cl:border-volt peer-checked/cl:bg-volt peer-checked/cl:text-ink peer-focus-visible/cl:ring-2 peer-focus-visible/cl:ring-volt">Claude</label>
                    <label for="tab-other" class="cursor-pointer select-none border border-chalk/20 px-5 py-2.5 font-display text-sm uppercase tracking-wider text-chalk-dim transition hover:border-chalk/50 hover:text-chalk peer-checked/oc:border-volt peer-checked/oc:bg-volt peer-checked/oc:text-ink peer-focus-visible/oc:ring-2 peer-focus-visible/oc:ring-volt">Any MCP client</label>

                    {{-- ChatGPT panel --}}
                    <div class="mt-6 hidden w-full border border-chalk/10 bg-ink-raised/60 p-6 peer-checked/cg:block sm:p-8">
                        <div class="flex flex-wrap items-center gap-2">
                            <span class="font-mono text-[10px] uppercase tracking-[0.2em] text-chalk-dim">MCP server URL</span>
                            <code id="mcp-url-chatgpt" class="break-all border border-chalk/15 bg-ink px-2 py-1 font-mono text-[13px] text-chalk">{{ $mcpUrl }}</code>
                            <button type="button" data-copy="mcp-url-chatgpt" class="cursor-pointer border border-chalk/25 px-2.5 py-1 font-mono text-[11px] uppercase tracking-wider text-chalk-dim transition hover:border-volt hover:text-volt">copy</button>
                        </div>
                        <ol class="mt-6 flex max-w-2xl flex-col gap-4">
                            <li class="flex gap-4"><span class="font-mono text-sm text-volt">01</span><p class="text-sm leading-relaxed text-chalk-dim">Open <span class="text-chalk">Settings → Apps &amp; Connectors → Advanced settings</span> and switch on <span class="text-chalk">Developer mode</span>. Custom connectors need a paid ChatGPT plan.</p></li>
                            <li class="flex gap-4"><span class="font-mono text-sm text-volt">02</span><p class="text-sm leading-relaxed text-chalk-dim">Back in <span class="text-chalk">Apps &amp; Connectors</span>, hit <span class="text-chalk">Create</span>. Name it <span class="text-chalk">Workout Memory</span>, paste the server URL above, set authentication to <span class="text-chalk">OAuth</span>, and create.</p></li>
                            <li class="flex gap-4"><span class="font-mono text-sm text-volt">03</span><p class="text-sm leading-relaxed text-chalk-dim">In a new chat, enable the connector (under developer-mode tools), then sign in here and approve when ChatGPT asks.</p></li>
                            <li class="flex gap-4"><span class="font-mono text-sm text-volt">04</span><p class="text-sm leading-relaxed text-chalk-dim">Say <span class="font-mono text-chalk">“log today's workout: …”</span> and that's it.</p></li>
                        </ol>
                    </div>

                    {{-- Claude panel --}}
                    <div class="mt-6 hidden w-full border border-chalk/10 bg-ink-raised/60 p-6 peer-checked/cl:block sm:p-8">
                        <div class="flex flex-wrap items-center gap-2">
                            <span class="font-mono text-[10px] uppercase tracking-[0.2em] text-chalk-dim">MCP server URL</span>
                            <code id="mcp-url-claude" class="break-all border border-chalk/15 bg-ink px-2 py-1 font-mono text-[13px] text-chalk">{{ $mcpUrl }}</code>
                            <button type="button" data-copy="mcp-url-claude" class="cursor-pointer border border-chalk/25 px-2.5 py-1 font-mono text-[11px] uppercase tracking-wider text-chalk-dim transition hover:border-volt hover:text-volt">copy</button>
                        </div>
                        <ol class="mt-6 flex max-w-2xl flex-col gap-4">
                            <li class="flex gap-4"><span class="font-mono text-sm text-volt">01</span><p class="text-sm leading-relaxed text-chalk-dim">On <span class="text-chalk">claude.ai</span> open <span class="text-chalk">Settings → Connectors → Add custom connector</span>.</p></li>
                            <li class="flex gap-4"><span class="font-mono text-sm text-volt">02</span><p class="text-sm leading-relaxed text-chalk-dim">Paste the server URL above and add it.</p></li>
                            <li class="flex gap-4"><span class="font-mono text-sm text-volt">03</span><p class="text-sm leading-relaxed text-chalk-dim">Hit <span class="text-chalk">Connect</span>, sign in here, approve. Done.</p></li>
                            <li class="flex gap-4"><span class="font-mono text-sm text-volt">04</span><p class="text-sm leading-relaxed text-chalk-dim">In any chat with the connector's tools enabled: <span class="font-mono text-chalk">“what did I bench last week?”</span></p></li>
                        </ol>
                    </div>

                    {{-- Other clients panel --}}
                    <div class="mt-6 hidden w-full border border-chalk/10 bg-ink-raised/60 p-6 peer-checked/oc:block sm:p-8">
                        <p class="max-w-2xl text-sm leading-relaxed text-chalk-dim">
                            Any MCP client that speaks streamable HTTP with OAuth discovery works. Point it at:
                        </p>
                        <div class="mt-4 flex flex-wrap items-center gap-2">
                            <code id="mcp-url-other" class="break-all border border-chalk/15 bg-ink px-2 py-1 font-mono text-[13px] text-chalk">{{ $mcpUrl }}</code>
                            <button type="button" data-copy="mcp-url-other" class="cursor-pointer border border-chalk/25 px-2.5 py-1 font-mono text-[11px] uppercase tracking-wider text-chalk-dim transition hover:border-volt hover:text-volt">copy</button>
                        </div>
                        <p class="mt-6 font-mono text-[10px] uppercase tracking-[0.2em] text-chalk-dim">Claude Code</p>
                        <div class="mt-2 flex flex-wrap items-center gap-2">
                            <code id="claude-code-cmd" class="break-all border border-chalk/15 bg-ink px-3 py-2 font-mono text-[13px] text-chalk">claude mcp add --transport http workout-memory {{ $mcpUrl }}</code>
                            <button type="button" data-copy="claude-code-cmd" class="cursor-pointer border border-chalk/25 px-2.5 py-1 font-mono text-[11px] uppercase tracking-wider text-chalk-dim transition hover:border-volt hover:text-volt">copy</button>
                        </div>
                        <p class="mt-3 text-sm leading-relaxed text-chalk-dim">Then run <span class="font-mono text-chalk">/mcp</span> to authenticate.</p>
                        <p class="mt-6 text-sm leading-relaxed text-chalk-dim">Agents can also read <a href="{{ route('llms') }}" class="font-mono text-chalk underline decoration-volt decoration-2 underline-offset-4 transition hover:text-volt">/llms.txt</a> for an overview written for them.</p>
                    </div>
                </div>

                {{-- Let your AI install it --}}
                <div class="mt-14">
                    <p class="font-mono text-xs uppercase tracking-[0.3em] text-volt">or let your AI install it for you</p>
                    <div class="mt-4 border border-chalk/15 bg-ink-raised p-6 sm:p-7">
                        <p class="text-sm leading-relaxed text-chalk-dim">
                            Don't want to hunt through menus? Hand your assistant this prompt. It knows its own settings
                            and will walk you through them.
                        </p>
                        <pre id="setup-prompt" class="mt-5 whitespace-pre-wrap border border-chalk/10 bg-ink p-4 font-mono text-[13px] leading-relaxed text-chalk/90">{{ $setupPrompt }}</pre>
                        <div class="mt-5 flex flex-wrap gap-3">
                            <button type="button" data-copy="setup-prompt" class="cursor-pointer bg-volt px-6 py-3 font-display text-sm uppercase tracking-wider text-ink transition hover:-translate-y-0.5 hover:bg-volt-hot">Copy prompt</button>
                            <a href="{{ $chatGptPromptUrl }}" target="_blank" rel="noopener" class="border border-chalk/25 px-6 py-3 font-display text-sm uppercase tracking-wider transition hover:-translate-y-0.5 hover:border-volt hover:text-volt">Open in ChatGPT ↗</a>
                            <a href="{{ $claudePromptUrl }}" target="_blank" rel="noopener" class="border border-chalk/25 px-6 py-3 font-display text-sm uppercase tracking-wider transition hover:-translate-y-0.5 hover:border-volt hover:text-volt">Open in Claude ↗</a>
                        </div>
                    </div>
                </div>
            </div>
        </section>

        {{-- ============ 05 · FAQ ============ --}}
        <section id="faq" class="scroll-mt-24 border-t border-chalk/10">
            <div class="mx-auto max-w-6xl px-5 py-20 sm:px-8 lg:py-24">
                <p class="font-mono text-xs uppercase tracking-[0.3em] text-volt">05 / faq</p>
                <h2 class="mt-4 font-display text-[clamp(2rem,4.5vw,3.4rem)] uppercase leading-[0.95]">Fast answers.</h2>
                <div class="mt-10 flex max-w-3xl flex-col divide-y divide-chalk/10 border-y border-chalk/10">
                    <details class="group">
                        <summary class="flex cursor-pointer list-none items-center justify-between gap-6 py-5 font-display text-lg uppercase tracking-wide [&::-webkit-details-marker]:hidden">
                            What do I need?
                            <span class="font-mono text-volt transition group-open:rotate-45">+</span>
                        </summary>
                        <p class="pb-6 text-sm leading-relaxed text-chalk-dim">An account here, plus ChatGPT (custom connectors via developer mode, on paid plans) or Claude (custom connectors). Claude Code and any other MCP client work too.</p>
                    </details>
                    <details class="group">
                        <summary class="flex cursor-pointer list-none items-center justify-between gap-6 py-5 font-display text-lg uppercase tracking-wide [&::-webkit-details-marker]:hidden">
                            Is it free?
                            <span class="font-mono text-volt transition group-open:rotate-45">+</span>
                        </summary>
                        <p class="pb-6 text-sm leading-relaxed text-chalk-dim">
                            Free during early access. Create an account, connect your AI, and start logging.
                        </p>
                    </details>
                    <details class="group">
                        <summary class="flex cursor-pointer list-none items-center justify-between gap-6 py-5 font-display text-lg uppercase tracking-wide [&::-webkit-details-marker]:hidden">
                            What gets stored, and where?
                            <span class="font-mono text-volt transition group-open:rotate-45">+</span>
                        </summary>
                        <p class="pb-6 text-sm leading-relaxed text-chalk-dim">Your workouts, exercises and the training context you choose to share — kept in your account on our EU-hosted server. Your AI reads it only through the tools you authorized, and you can browse everything after signing in on the web. Workouts stay private unless you ask for a share link, and you can revoke one anytime.</p>
                    </details>
                    <details class="group">
                        <summary class="flex cursor-pointer list-none items-center justify-between gap-6 py-5 font-display text-lg uppercase tracking-wide [&::-webkit-details-marker]:hidden">
                            What if it gets an exercise wrong?
                            <span class="font-mono text-volt transition group-open:rotate-45">+</span>
                        </summary>
                        <p class="pb-6 text-sm leading-relaxed text-chalk-dim">It flags assumptions instead of guessing silently. Say “that was front squats, not back squats” — the log gets fixed and your phrasing is remembered for next time.</p>
                    </details>
                    <details class="group">
                        <summary class="flex cursor-pointer list-none items-center justify-between gap-6 py-5 font-display text-lg uppercase tracking-wide [&::-webkit-details-marker]:hidden">
                            Can I add old workouts from my notes?
                            <span class="font-mono text-volt transition group-open:rotate-45">+</span>
                        </summary>
                        <p class="pb-6 text-sm leading-relaxed text-chalk-dim">Yes. After connecting Workout Memory, paste a chunk of your old training notes or CSV-like data into ChatGPT or Claude and ask it to add them. The assistant splits the text into normal dated session logs, so start with a week or month at a time if the file is large.</p>
                    </details>
                    <details class="group">
                        <summary class="flex cursor-pointer list-none items-center justify-between gap-6 py-5 font-display text-lg uppercase tracking-wide [&::-webkit-details-marker]:hidden">
                            Do I have to log live at the gym?
                            <span class="font-mono text-volt transition group-open:rotate-45">+</span>
                        </summary>
                        <p class="pb-6 text-sm leading-relaxed text-chalk-dim">No. Dump the whole session in one message afterwards, or log set-by-set as you go — both land in the same history. Live sessions auto-close after 18 hours if you forget.</p>
                    </details>
                </div>
            </div>
        </section>
    </main>

    <footer class="border-t border-chalk/10">
        <div class="mx-auto flex max-w-6xl flex-col justify-between gap-8 px-5 py-12 sm:flex-row sm:items-end sm:px-8">
            <div>
                <div class="flex items-center gap-3">
                    <svg viewBox="0 0 32 32" class="h-6 w-6" aria-hidden="true">
                        <rect width="32" height="32" rx="6" class="fill-volt"/>
                        <rect x="4" y="14" width="24" height="4" rx="2" class="fill-ink"/>
                        <rect x="7" y="9" width="4" height="14" rx="1.5" class="fill-ink"/>
                        <rect x="21" y="9" width="4" height="14" rx="1.5" class="fill-ink"/>
                    </svg>
                    <span class="font-display text-base uppercase tracking-wide">Workout Memory</span>
                </div>
                <p class="mt-3 max-w-sm text-xs leading-relaxed text-chalk-dim">An MCP server by Remics Software Technologies - FZCO. Built for people who'd rather train than tap.</p>
            </div>
            <div class="flex flex-col gap-2 font-mono text-xs text-chalk-dim sm:items-end">
                <code class="break-all">{{ $mcpUrl }}</code>
                <a href="{{ route('docs') }}" class="transition hover:text-volt">Docs →</a>
                <a href="{{ route('llms') }}" class="transition hover:text-volt">llms.txt →</a>
                <a href="{{ route('privacy') }}" class="transition hover:text-volt">Privacy →</a>
                <a href="{{ route('terms') }}" class="transition hover:text-volt">Terms →</a>
                <a href="{{ route('support') }}" class="transition hover:text-volt">Support →</a>
                @auth
                    <a href="{{ route('home') }}" class="transition hover:text-volt">Dashboard →</a>
                @else
                    <a href="{{ route('login') }}" class="transition hover:text-volt">Sign in →</a>
                @endauth
                <span>© {{ now()->year }} Remics Software Technologies - FZCO</span>
            </div>
        </div>
    </footer>

    <script>
        document.querySelectorAll('[data-copy]').forEach((button) => {
            button.addEventListener('click', () => {
                const source = document.getElementById(button.dataset.copy);

                if (! source) {
                    return;
                }

                navigator.clipboard.writeText(source.innerText.trim()).then(() => {
                    const original = button.innerText;
                    button.innerText = 'copied ✓';
                    setTimeout(() => { button.innerText = original; }, 1600);
                });
            });
        });

        document.querySelectorAll('[data-tab]').forEach((link) => {
            link.addEventListener('click', () => {
                const radio = document.getElementById(link.dataset.tab);

                if (radio) {
                    radio.checked = true;
                }
            });
        });
    </script>
</body>
</html>
