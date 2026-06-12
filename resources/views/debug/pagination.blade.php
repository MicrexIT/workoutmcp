@if ($paginator->hasPages())
    <nav role="navigation" aria-label="Pagination" class="flex flex-wrap items-center justify-between gap-4 font-mono text-xs text-chalk-dim">
        <span>{{ $paginator->firstItem() }} to {{ $paginator->lastItem() }} of {{ $paginator->total() }}</span>
        <div class="flex gap-2">
            @if ($paginator->onFirstPage())
                <span class="border border-chalk/10 px-3 py-1.5 uppercase tracking-wider text-chalk-dim/50">Prev</span>
            @else
                <a href="{{ $paginator->previousPageUrl() }}" rel="prev" class="border border-chalk/25 px-3 py-1.5 uppercase tracking-wider transition hover:border-volt hover:text-volt">Prev</a>
            @endif

            @if ($paginator->hasMorePages())
                <a href="{{ $paginator->nextPageUrl() }}" rel="next" class="border border-chalk/25 px-3 py-1.5 uppercase tracking-wider transition hover:border-volt hover:text-volt">Next</a>
            @else
                <span class="border border-chalk/10 px-3 py-1.5 uppercase tracking-wider text-chalk-dim/50">Next</span>
            @endif
        </div>
    </nav>
@endif
