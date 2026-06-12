@extends('debug.layout', ['title' => 'Exercises'])

@section('content')
    <p class="font-mono text-xs uppercase tracking-[0.3em] text-volt">catalog</p>
    <h1 class="mt-3 font-display text-4xl uppercase leading-none sm:text-5xl">Exercises</h1>

    <div class="mt-8 overflow-x-auto border border-chalk/10 bg-ink-raised/40">
        <table class="w-full text-sm">
            <thead>
                <tr>
                    <th class="border-b border-chalk/15 px-4 py-3 text-left font-mono text-[10px] font-normal uppercase tracking-[0.18em] text-chalk-dim">Name</th>
                    <th class="border-b border-chalk/15 px-4 py-3 text-left font-mono text-[10px] font-normal uppercase tracking-[0.18em] text-chalk-dim">Category</th>
                    <th class="border-b border-chalk/15 px-4 py-3 text-left font-mono text-[10px] font-normal uppercase tracking-[0.18em] text-chalk-dim">Granularity</th>
                    <th class="border-b border-chalk/15 px-4 py-3 text-left font-mono text-[10px] font-normal uppercase tracking-[0.18em] text-chalk-dim">Tracking</th>
                    <th class="border-b border-chalk/15 px-4 py-3 text-left font-mono text-[10px] font-normal uppercase tracking-[0.18em] text-chalk-dim">Aliases</th>
                    <th class="border-b border-chalk/15 px-4 py-3 text-left font-mono text-[10px] font-normal uppercase tracking-[0.18em] text-chalk-dim">Parent</th>
                </tr>
            </thead>
            <tbody>
                @foreach ($exercises as $exercise)
                    <tr class="transition hover:bg-chalk/[0.03]">
                        <td class="border-b border-chalk/5 px-4 py-2.5 align-top">{{ $exercise->name }}</td>
                        <td class="border-b border-chalk/5 px-4 py-2.5 align-top font-mono text-xs text-chalk-dim">{{ $exercise->category }}</td>
                        <td class="border-b border-chalk/5 px-4 py-2.5 align-top font-mono text-xs text-chalk-dim">{{ $exercise->granularity }}</td>
                        <td class="border-b border-chalk/5 px-4 py-2.5 align-top font-mono text-xs text-chalk-dim">{{ $exercise->tracking_mode }}</td>
                        <td class="border-b border-chalk/5 px-4 py-2.5 align-top text-xs text-chalk-dim">{{ $exercise->aliases->pluck('alias')->join(', ') }}</td>
                        <td class="border-b border-chalk/5 px-4 py-2.5 align-top text-xs text-chalk-dim">{{ $exercise->parent?->name }}</td>
                    </tr>
                @endforeach
            </tbody>
        </table>
    </div>

    <div class="mt-6">{{ $exercises->links('debug.pagination') }}</div>
@endsection
