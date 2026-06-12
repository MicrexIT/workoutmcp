@extends('auth.layout')

@section('title', 'Sign In')

@section('card')
    <h1 class="font-display text-2xl uppercase tracking-wide">Sign in</h1>
    <p class="mt-2 text-sm leading-relaxed text-chalk-dim">Use your Workout Memory account to manage the MCP server.</p>

    <form method="POST" action="{{ route('login.store') }}" class="mt-6">
        @csrf

        <label for="email" class="block font-mono text-[11px] uppercase tracking-[0.18em] text-chalk-dim">Email</label>
        <input id="email" name="email" type="email" value="{{ old('email') }}" autocomplete="email" required autofocus
            class="mt-2 w-full border border-chalk/20 bg-ink px-3.5 py-2.5 text-sm text-chalk transition focus:border-volt focus:outline-none focus:ring-1 focus:ring-volt/50">
        @error('email')
            <p class="mt-1.5 text-xs text-red-300">{{ $message }}</p>
        @enderror

        <label for="password" class="mt-5 block font-mono text-[11px] uppercase tracking-[0.18em] text-chalk-dim">Password</label>
        <input id="password" name="password" type="password" autocomplete="current-password" required
            class="mt-2 w-full border border-chalk/20 bg-ink px-3.5 py-2.5 text-sm text-chalk transition focus:border-volt focus:outline-none focus:ring-1 focus:ring-volt/50">
        @error('password')
            <p class="mt-1.5 text-xs text-red-300">{{ $message }}</p>
        @enderror

        <label for="remember" class="mt-5 flex cursor-pointer items-center gap-2.5 text-sm text-chalk-dim">
            <input id="remember" name="remember" type="checkbox" value="1" class="h-4 w-4 accent-volt">
            Remember me
        </label>

        <button type="submit" class="mt-7 w-full cursor-pointer bg-volt px-6 py-3 font-display text-base uppercase tracking-wider text-ink transition hover:bg-volt-hot">Sign in</button>
    </form>
@endsection

@if ($registrationOpen)
    @section('footer')
        <a href="{{ route('register') }}" class="text-volt underline underline-offset-4 transition hover:text-volt-hot">Create another account</a>
    @endsection
@endif
