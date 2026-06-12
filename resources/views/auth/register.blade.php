@extends('auth.layout')

@section('title', 'Create Account')

@section('card')
    <h1 class="font-display text-2xl uppercase tracking-wide">Create account</h1>
    <p class="mt-2 text-sm leading-relaxed text-chalk-dim">This account approves your AI's access and protects your training log.</p>

    <form method="POST" action="{{ route('register.store') }}" class="mt-6">
        @csrf

        <label for="name" class="block font-mono text-[11px] uppercase tracking-[0.18em] text-chalk-dim">Name</label>
        <input id="name" name="name" type="text" value="{{ old('name') }}" autocomplete="name" required autofocus
            class="mt-2 w-full border border-chalk/20 bg-ink px-3.5 py-2.5 text-sm text-chalk transition focus:border-volt focus:outline-none focus:ring-1 focus:ring-volt/50">
        @error('name')
            <p class="mt-1.5 text-xs text-red-300">{{ $message }}</p>
        @enderror

        <label for="email" class="mt-5 block font-mono text-[11px] uppercase tracking-[0.18em] text-chalk-dim">Email</label>
        <input id="email" name="email" type="email" value="{{ old('email') }}" autocomplete="email" required
            class="mt-2 w-full border border-chalk/20 bg-ink px-3.5 py-2.5 text-sm text-chalk transition focus:border-volt focus:outline-none focus:ring-1 focus:ring-volt/50">
        @error('email')
            <p class="mt-1.5 text-xs text-red-300">{{ $message }}</p>
        @enderror

        <label for="password" class="mt-5 block font-mono text-[11px] uppercase tracking-[0.18em] text-chalk-dim">Password</label>
        <input id="password" name="password" type="password" autocomplete="new-password" required
            class="mt-2 w-full border border-chalk/20 bg-ink px-3.5 py-2.5 text-sm text-chalk transition focus:border-volt focus:outline-none focus:ring-1 focus:ring-volt/50">
        @error('password')
            <p class="mt-1.5 text-xs text-red-300">{{ $message }}</p>
        @enderror

        <label for="password_confirmation" class="mt-5 block font-mono text-[11px] uppercase tracking-[0.18em] text-chalk-dim">Confirm password</label>
        <input id="password_confirmation" name="password_confirmation" type="password" autocomplete="new-password" required
            class="mt-2 w-full border border-chalk/20 bg-ink px-3.5 py-2.5 text-sm text-chalk transition focus:border-volt focus:outline-none focus:ring-1 focus:ring-volt/50">

        <button type="submit" class="mt-7 w-full cursor-pointer bg-volt px-6 py-3 font-display text-base uppercase tracking-wider text-ink transition hover:bg-volt-hot">Create account</button>
    </form>
@endsection

@section('footer')
    Already have an account? <a href="{{ route('login') }}" class="text-volt underline underline-offset-4 transition hover:text-volt-hot">Sign in</a>
@endsection
