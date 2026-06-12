@extends('debug.layout', ['title' => 'Workouts'])

@section('content')
    <p class="font-mono text-xs uppercase tracking-[0.3em] text-volt">training log</p>
    <h1 class="mt-3 font-display text-4xl uppercase leading-none sm:text-5xl">Workouts</h1>

    <div class="mt-8 overflow-x-auto border border-chalk/10 bg-ink-raised/40">
        <table class="w-full text-sm">
            <thead>
                <tr>
                    <th class="border-b border-chalk/15 px-4 py-3 text-left font-mono text-[10px] font-normal uppercase tracking-[0.18em] text-chalk-dim">Name</th>
                    <th class="border-b border-chalk/15 px-4 py-3 text-left font-mono text-[10px] font-normal uppercase tracking-[0.18em] text-chalk-dim">Kind</th>
                    <th class="border-b border-chalk/15 px-4 py-3 text-left font-mono text-[10px] font-normal uppercase tracking-[0.18em] text-chalk-dim">Started</th>
                    <th class="border-b border-chalk/15 px-4 py-3 text-left font-mono text-[10px] font-normal uppercase tracking-[0.18em] text-chalk-dim">Exercises</th>
                    <th class="border-b border-chalk/15 px-4 py-3 text-left font-mono text-[10px] font-normal uppercase tracking-[0.18em] text-chalk-dim">Sets</th>
                    <th class="border-b border-chalk/15 px-4 py-3 text-right font-mono text-[10px] font-normal uppercase tracking-[0.18em] text-chalk-dim">Actions</th>
                </tr>
            </thead>
            <tbody>
                @forelse ($workouts as $workout)
                    <tr class="transition hover:bg-chalk/[0.03]">
                        <td class="border-b border-chalk/5 px-4 py-3 align-top"><a href="{{ route('workouts.show', $workout['id']) }}" class="text-chalk underline decoration-volt decoration-2 underline-offset-4 transition hover:text-volt">{{ $workout['name'] ?? 'Workout' }}</a></td>
                        <td class="border-b border-chalk/5 px-4 py-3 align-top text-chalk-dim">{{ $workout['kind'] }}</td>
                        <td class="border-b border-chalk/5 px-4 py-3 align-top whitespace-nowrap font-mono text-xs text-chalk-dim">{{ $workout['started_at'] }}</td>
                        <td class="border-b border-chalk/5 px-4 py-3 align-top text-chalk-dim">{{ implode(', ', $workout['exercise_names']) }}</td>
                        <td class="border-b border-chalk/5 px-4 py-3 align-top font-mono">{{ $workout['set_count'] }}</td>
                        <td class="border-b border-chalk/5 px-4 py-3 text-right align-top">
                            <form class="inline" action="{{ route('workouts.destroy', $workout['id']) }}" method="POST">
                                @csrf
                                @method('DELETE')
                                <button type="submit" class="cursor-pointer border border-red-400/40 px-3 py-1.5 font-mono text-[11px] uppercase tracking-wider text-red-300 transition hover:bg-red-400/10">Delete</button>
                            </form>
                        </td>
                    </tr>
                @empty
                    <tr>
                        <td colspan="6" class="px-4 py-8 text-center text-chalk-dim">No workouts logged yet.</td>
                    </tr>
                @endforelse
            </tbody>
        </table>
    </div>
@endsection
