<!doctype html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>{{ $title ?? 'Workout Memory MCP' }}</title>
    <style>
        :root { color-scheme: light; font-family: ui-sans-serif, system-ui, -apple-system, BlinkMacSystemFont, "Segoe UI", sans-serif; }
        body { margin: 0; background: #f6f7f9; color: #15171a; }
        header { background: #ffffff; border-bottom: 1px solid #dfe3e8; }
        nav, main { max-width: 1120px; margin: 0 auto; padding: 18px 20px; }
        nav { display: flex; align-items: center; justify-content: space-between; gap: 16px; }
        nav > div { align-items: center; display: flex; flex-wrap: wrap; gap: 8px; justify-content: flex-end; }
        nav a { color: #1f5eff; text-decoration: none; font-weight: 650; }
        .brand { color: #15171a; font-weight: 800; }
        h1 { margin: 0 0 14px; font-size: 28px; line-height: 1.15; }
        h2 { margin-top: 28px; font-size: 18px; }
        .panel { background: #ffffff; border: 1px solid #dfe3e8; border-radius: 8px; padding: 18px; margin: 16px 0; }
        .grid { display: grid; grid-template-columns: repeat(auto-fit, minmax(220px, 1fr)); gap: 12px; }
        .metric { background: #ffffff; border: 1px solid #dfe3e8; border-radius: 8px; padding: 16px; }
        .metric strong { display: block; font-size: 26px; }
        table { width: 100%; border-collapse: collapse; background: #ffffff; border: 1px solid #dfe3e8; border-radius: 8px; overflow: hidden; }
        th, td { text-align: left; padding: 10px 12px; border-bottom: 1px solid #edf0f3; vertical-align: top; }
        th { background: #f0f2f5; font-size: 12px; text-transform: uppercase; letter-spacing: .04em; }
        button { border: 1px solid #cfd6df; border-radius: 6px; background: #ffffff; color: #15171a; cursor: pointer; font: inherit; font-weight: 650; padding: 7px 10px; }
        button:hover { background: #f6f7f9; }
        .danger-button { border-color: #f0b4b4; color: #b42318; }
        .danger-button:hover { background: #fff1f1; }
        code { background: #eef1f5; border-radius: 5px; padding: 2px 5px; }
        .alert { border: 1px solid #bbd7b7; background: #f0faef; border-radius: 8px; color: #245b22; margin-bottom: 16px; padding: 12px 14px; }
        .alert-error { border-color: #f0b4b4; background: #fff1f1; color: #b42318; }
        .inline-form { display: inline; margin: 0; }
        .muted { color: #667085; }
        .nowrap { white-space: nowrap; }
        .page-heading { align-items: flex-start; display: flex; gap: 16px; justify-content: space-between; margin-bottom: 14px; }
        .page-heading h1 { margin-bottom: 0; }
        .text-right { text-align: right; }
        .logout-button { border: 0; color: #667085; padding: 0; }
        .logout-button:hover { background: transparent; color: #15171a; }
    </style>
</head>
<body>
    <header>
        <nav>
            <a class="brand" href="{{ route('home') }}">Workout Memory MCP</a>
            <div>
                <a href="{{ route('exercises.index') }}">Exercises</a>
                <span class="muted"> · </span>
                <a href="{{ route('workouts.index') }}">Workouts</a>
                <span class="muted"> · </span>
                <span class="muted">{{ auth()->user()->email }}</span>
                <form method="POST" action="{{ route('logout') }}" class="inline-form">
                    @csrf
                    <button type="submit" class="logout-button">Sign out</button>
                </form>
            </div>
        </nav>
    </header>
    <main>
        @if (session('status'))
            <div class="alert">{{ session('status') }}</div>
        @endif
        @if (session('error'))
            <div class="alert alert-error">{{ session('error') }}</div>
        @endif
        @yield('content')
    </main>
</body>
</html>
