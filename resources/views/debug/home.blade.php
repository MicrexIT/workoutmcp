@extends('debug.layout', ['title' => 'Workout Memory MCP'])

@section('content')
    <h1>Workout Memory MCP</h1>
    <p class="muted">Private ChatGPT-first workout memory layer. The primary interface is MCP; these pages are read-only debugging aids.</p>

    <section class="grid">
        <div class="metric">
            <span class="muted">Seeded exercises</span>
            <strong>{{ $exerciseCount }}</strong>
        </div>
        <div class="metric">
            <span class="muted">Logged workouts</span>
            <strong>{{ $workoutCount }}</strong>
        </div>
        <div class="metric">
            <span class="muted">MCP endpoint</span>
            <strong style="font-size: 16px;"><code>/mcp/workout-memory</code></strong>
        </div>
    </section>

    <section class="panel">
        <h2>Single-user context</h2>
        <p><strong>{{ $user->name }}</strong> <span class="muted">{{ $user->email }}</span></p>
        <p class="muted">{{ $user->profile?->goals }}</p>
    </section>
@endsection
