<!doctype html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Sign In · Workout Memory MCP</title>
    <style>
        :root { color-scheme: light; font-family: ui-sans-serif, system-ui, -apple-system, BlinkMacSystemFont, "Segoe UI", sans-serif; }
        body { background: #f6f7f9; color: #15171a; margin: 0; }
        main { margin: 8vh auto; max-width: 440px; padding: 0 24px; }
        section { background: #fff; border: 1px solid #d9dde3; border-radius: 8px; padding: 28px; }
        h1 { font-size: 24px; line-height: 1.2; margin: 0 0 8px; }
        p { color: #59606a; line-height: 1.5; margin: 0 0 18px; }
        label { display: block; font-weight: 650; margin: 16px 0 8px; }
        input[type="email"], input[type="password"] { border: 1px solid #b8bec8; border-radius: 6px; box-sizing: border-box; font: inherit; padding: 10px 12px; width: 100%; }
        button { background: #0f766e; border: 0; border-radius: 6px; color: #fff; cursor: pointer; font: inherit; font-weight: 700; margin-top: 18px; padding: 11px 16px; width: 100%; }
        a { color: #1f5eff; font-weight: 650; text-decoration: none; }
        .alert { border: 1px solid #bbd7b7; background: #f0faef; border-radius: 8px; color: #245b22; margin-bottom: 16px; padding: 12px 14px; }
        .error { color: #9f1239; font-size: 14px; margin-top: 6px; }
        .checkbox { align-items: center; display: flex; gap: 8px; margin-top: 16px; }
        .checkbox label { margin: 0; }
        .footer { margin-top: 18px; text-align: center; }
    </style>
</head>
<body>
    <main>
        @if (session('status'))
            <div class="alert">{{ session('status') }}</div>
        @endif

        <section>
            <h1>Sign in</h1>
            <p>Use your Workout Memory account to manage the MCP server.</p>

            <form method="POST" action="{{ route('login.store') }}">
                @csrf

                <label for="email">Email</label>
                <input id="email" name="email" type="email" value="{{ old('email') }}" autocomplete="email" required autofocus>
                @error('email')
                    <div class="error">{{ $message }}</div>
                @enderror

                <label for="password">Password</label>
                <input id="password" name="password" type="password" autocomplete="current-password" required>
                @error('password')
                    <div class="error">{{ $message }}</div>
                @enderror

                <div class="checkbox">
                    <input id="remember" name="remember" type="checkbox" value="1">
                    <label for="remember">Remember me</label>
                </div>

                <button type="submit">Sign in</button>
            </form>

            @if ($registrationOpen)
                <p class="footer"><a href="{{ route('register') }}">Create another account</a></p>
            @endif
        </section>
    </main>
</body>
</html>
