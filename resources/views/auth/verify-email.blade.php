@extends('auth.layout')

@section('title', 'Verify Email')

@section('card')
    <h1 class="font-display text-2xl uppercase tracking-wide">Verify email</h1>
    <p class="mt-2 text-sm leading-relaxed text-chalk-dim">
        Check your inbox for the verification link before connecting apps or using Workout Memory.
    </p>

    <form method="POST" action="{{ route('verification.send') }}" class="mt-6">
        @csrf

        <button type="submit" class="w-full cursor-pointer bg-volt px-6 py-3 font-display text-base uppercase tracking-wider text-ink transition hover:bg-volt-hot">Send link again</button>
    </form>

    <form method="POST" action="{{ route('logout') }}" class="mt-3">
        @csrf

        <button type="submit" class="w-full cursor-pointer border border-chalk/20 px-6 py-3 font-display text-base uppercase tracking-wider text-chalk transition hover:border-chalk/40">Sign out</button>
    </form>
@endsection

@section('footer')
    Signed in as {{ auth()->user()->email }}
@endsection
