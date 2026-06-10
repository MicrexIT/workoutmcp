<!doctype html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Create Account · Workout Memory MCP</title>
    <style>
        :root { color-scheme: light; font-family: ui-sans-serif, system-ui, -apple-system, BlinkMacSystemFont, "Segoe UI", sans-serif; }
        body { background: #f6f7f9; color: #15171a; margin: 0; }
        main { margin: 8vh auto; max-width: 460px; padding: 0 24px; }
        section { background: #fff; border: 1px solid #d9dde3; border-radius: 8px; padding: 28px; }
        h1 { font-size: 24px; line-height: 1.2; margin: 0 0 8px; }
        p { color: #59606a; line-height: 1.5; margin: 0 0 18px; }
        label { display: block; font-weight: 650; margin: 16px 0 8px; }
        input { border: 1px solid #b8bec8; border-radius: 6px; box-sizing: border-box; font: inherit; padding: 10px 12px; width: 100%; }
        button { background: #0f766e; border: 0; border-radius: 6px; color: #fff; cursor: pointer; font: inherit; font-weight: 700; margin-top: 18px; padding: 11px 16px; width: 100%; }
        .error { color: #9f1239; font-size: 14px; margin-top: 6px; }
    </style>
</head>
<body>
    <main>
        <section>
            <h1>Create account</h1>
            <p>This account approves ChatGPT access and protects your workout dashboard.</p>

            <form method="POST" action="{{ route('register.store') }}">
                @csrf

                <label for="name">Name</label>
                <input id="name" name="name" type="text" value="{{ old('name') }}" autocomplete="name" required autofocus>
                @error('name')
                    <div class="error">{{ $message }}</div>
                @enderror

                <label for="email">Email</label>
                <input id="email" name="email" type="email" value="{{ old('email') }}" autocomplete="email" required>
                @error('email')
                    <div class="error">{{ $message }}</div>
                @enderror

                <label for="password">Password</label>
                <input id="password" name="password" type="password" autocomplete="new-password" required>
                @error('password')
                    <div class="error">{{ $message }}</div>
                @enderror

                <label for="password_confirmation">Confirm password</label>
                <input id="password_confirmation" name="password_confirmation" type="password" autocomplete="new-password" required>

                <button type="submit">Create account</button>
            </form>
        </section>
    </main>
</body>
</html>
