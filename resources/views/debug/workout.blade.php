@extends('debug.layout', ['title' => $workout['name'] ?? 'Workout'])

@section('content')
    <div class="page-heading">
        <div>
            <h1>{{ $workout['name'] ?? 'Workout' }}</h1>
            <p class="muted">{{ $workout['kind'] }} · {{ $workout['started_at'] }} · {{ $workout['set_count'] }} sets</p>
        </div>
        <form class="inline-form" action="{{ route('workouts.destroy', $workout['id']) }}" method="POST">
            @csrf
            @method('DELETE')
            <button class="danger-button" type="submit">Delete</button>
        </form>
    </div>

    @foreach ($workout['exercises'] as $exercise)
        <section class="panel">
            <h2>{{ $exercise['name'] }}</h2>
            @if ($exercise['variant_label'] || $exercise['variant_description'])
                <p><strong>{{ $exercise['variant_label'] }}</strong> <span class="muted">{{ $exercise['variant_description'] }}</span></p>
            @endif
            @if ($exercise['notes'])
                <p>{{ $exercise['notes'] }}</p>
            @endif
            <table>
                <thead>
                    <tr>
                        <th>Set</th>
                        <th>Reps</th>
                        <th>Load kg</th>
                        <th>Duration</th>
                        <th>Distance</th>
                        <th>Notes</th>
                    </tr>
                </thead>
                <tbody>
                    @foreach ($exercise['sets'] as $set)
                        <tr>
                            <td>{{ $set['set_number'] }}</td>
                            <td>{{ $set['reps'] }}</td>
                            <td>{{ $set['load_kg'] }}</td>
                            <td>{{ $set['duration_seconds'] }}</td>
                            <td>{{ $set['distance_meters'] }}</td>
                            <td>{{ $set['notes'] ?? $set['raw_set_text'] }}</td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
        </section>
    @endforeach
@endsection
