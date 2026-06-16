@extends('debug.layout', ['title' => $workout['name'] ?? 'Workout'])

@section('content')
    <div class="flex flex-wrap items-start justify-between gap-4">
        <div>
            <p class="font-mono text-xs uppercase tracking-[0.3em] text-volt">workout</p>
            <h1 class="mt-3 font-display text-4xl uppercase leading-none sm:text-5xl">{{ $workout['name'] ?? 'Workout' }}</h1>
            <p class="mt-3 font-mono text-xs text-chalk-dim">{{ $workout['kind'] }} · {{ $workout['started_at'] }} · {{ $workout['set_count'] }} sets</p>
        </div>
        <form class="inline" action="{{ route('workouts.destroy', $workout['id']) }}" method="POST">
            @csrf
            @method('DELETE')
            <button type="submit" class="cursor-pointer border border-red-400/40 px-3 py-1.5 font-mono text-[11px] uppercase tracking-wider text-red-300 transition hover:bg-red-400/10">Delete</button>
        </form>
    </div>

    <section class="mt-6 border border-chalk/10 bg-ink-raised/60 p-5">
        <div class="flex flex-wrap items-center justify-between gap-3">
            <p class="font-mono text-[10px] uppercase tracking-[0.2em] text-chalk-dim">Public share link</p>
            @if ($share)
                <form class="inline" action="{{ route('workouts.share.destroy', $workout['id']) }}" method="POST">
                    @csrf
                    @method('DELETE')
                    <button type="submit" class="cursor-pointer border border-red-400/40 px-3 py-1.5 font-mono text-[11px] uppercase tracking-wider text-red-300 transition hover:bg-red-400/10">Revoke</button>
                </form>
            @else
                <form class="inline" action="{{ route('workouts.share.store', $workout['id']) }}" method="POST">
                    @csrf
                    <button type="submit" class="cursor-pointer border border-chalk/25 px-3 py-1.5 font-mono text-[11px] uppercase tracking-wider transition hover:border-volt hover:text-volt">Create share link</button>
                </form>
            @endif
        </div>
        @if ($share)
            <a href="{{ $share->publicUrl() }}" target="_blank" rel="noopener" class="mt-3 block break-all font-mono text-sm text-volt underline underline-offset-4">{{ $share->publicUrl() }}</a>
        @else
            <p class="mt-3 text-sm text-chalk-dim">This workout is private. Create a link to share a read-only view, revocable anytime.</p>
        @endif
    </section>

    @foreach ($workout['exercises'] as $exercise)
        <section class="mt-6 border border-chalk/10 bg-ink-raised/60 p-6">
            <h2 class="font-display text-xl uppercase tracking-wide">{{ $exercise['name'] }}</h2>
            @if ($exercise['variant_label'] || $exercise['variant_description'])
                <p class="mt-2 text-sm"><strong>{{ $exercise['variant_label'] }}</strong> <span class="text-chalk-dim">{{ $exercise['variant_description'] }}</span></p>
            @endif
            @if ($exercise['notes'])
                <p class="mt-2 max-w-2xl text-sm leading-relaxed text-chalk-dim">{{ $exercise['notes'] }}</p>
            @endif
            <div class="mt-4 overflow-x-auto">
                <table class="w-full text-sm">
                    <thead>
                        <tr>
                            <th class="border-b border-chalk/15 px-3 py-2 text-left font-mono text-[10px] font-normal uppercase tracking-[0.18em] text-chalk-dim">Set</th>
                            <th class="border-b border-chalk/15 px-3 py-2 text-left font-mono text-[10px] font-normal uppercase tracking-[0.18em] text-chalk-dim">Reps</th>
                            <th class="border-b border-chalk/15 px-3 py-2 text-left font-mono text-[10px] font-normal uppercase tracking-[0.18em] text-chalk-dim">Load kg</th>
                            <th class="border-b border-chalk/15 px-3 py-2 text-left font-mono text-[10px] font-normal uppercase tracking-[0.18em] text-chalk-dim">Duration</th>
                            <th class="border-b border-chalk/15 px-3 py-2 text-left font-mono text-[10px] font-normal uppercase tracking-[0.18em] text-chalk-dim">Distance</th>
                            <th class="border-b border-chalk/15 px-3 py-2 text-left font-mono text-[10px] font-normal uppercase tracking-[0.18em] text-chalk-dim">Notes</th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach ($exercise['sets'] as $set)
                            <tr class="transition hover:bg-chalk/[0.03]">
                                <td class="border-b border-chalk/5 px-3 py-2 font-mono text-xs text-volt">{{ $set['set_number'] }}</td>
                                <td class="border-b border-chalk/5 px-3 py-2 font-mono">{{ $set['reps'] }}</td>
                                <td class="border-b border-chalk/5 px-3 py-2 font-mono">{{ $set['load_kg'] }}</td>
                                <td class="border-b border-chalk/5 px-3 py-2 font-mono text-chalk-dim">{{ $set['duration_display'] }}</td>
                                <td class="border-b border-chalk/5 px-3 py-2 font-mono text-chalk-dim">{{ $set['distance_meters'] }}</td>
                                <td class="border-b border-chalk/5 px-3 py-2 text-xs text-chalk-dim">{{ $set['notes'] ?? $set['raw_set_text'] }}</td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
        </section>
    @endforeach
@endsection
