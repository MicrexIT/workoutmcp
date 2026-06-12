@extends('auth.layout')

@section('title', 'Authorize Access')

@section('card')
    <h1 class="font-display text-2xl uppercase tracking-wide">Authorize access</h1>
    <p class="mt-2 text-sm leading-relaxed text-chalk-dim">
        <span class="text-chalk">{{ $authorization['client_name'] }}</span> wants to connect to your Workout Memory account.
    </p>

    <dl class="mt-6 space-y-4 border border-chalk/12 bg-ink px-4 py-4">
        <div>
            <dt class="font-mono text-[11px] uppercase tracking-[0.18em] text-chalk-dim">It will be able to</dt>
            <dd class="mt-1 text-sm text-chalk">Log workouts and read your training history</dd>
        </div>
        <div>
            <dt class="font-mono text-[11px] uppercase tracking-[0.18em] text-chalk-dim">Sends you back to</dt>
            <dd class="mt-1 text-sm text-chalk">
                @if ($destination['type'] === 'web')
                    {{ $destination['target'] }}
                @elseif ($destination['type'] === 'loopback')
                    A local app on this device ({{ $destination['target'] }})
                @else
                    The app that handles {{ $destination['target'] }} links on this device
                @endif
            </dd>
        </div>
    </dl>

    <p class="mt-4 text-xs leading-relaxed text-chalk-dim">Only approve if you just started this connection from an app you trust.</p>

    <form method="POST" action="{{ route('workout-memory.oauth.authorize.decision') }}" class="mt-6 flex gap-3">
        @csrf
        @foreach (['response_type', 'client_id', 'redirect_uri', 'scope', 'state', 'code_challenge', 'code_challenge_method', 'resource'] as $field)
            @if (filled($authorization[$field] ?? null))
                <input type="hidden" name="{{ $field }}" value="{{ $authorization[$field] }}">
            @endif
        @endforeach
        <button type="submit" name="action" value="deny" class="flex-1 cursor-pointer border border-chalk/20 px-6 py-3 font-display text-base uppercase tracking-wider text-chalk transition hover:border-chalk/40">Deny</button>
        <button type="submit" name="action" value="approve" class="flex-1 cursor-pointer bg-volt px-6 py-3 font-display text-base uppercase tracking-wider text-ink transition hover:bg-volt-hot">Approve</button>
    </form>
@endsection

@section('footer')
    Signed in as {{ auth()->user()->email }}
@endsection
