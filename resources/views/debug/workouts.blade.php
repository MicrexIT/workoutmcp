@extends('debug.layout', ['title' => 'Workouts'])

@section('content')
    <h1>Workouts</h1>
    <table>
        <thead>
            <tr>
                <th>Name</th>
                <th>Kind</th>
                <th>Started</th>
                <th>Exercises</th>
                <th>Sets</th>
                <th class="text-right">Actions</th>
            </tr>
        </thead>
        <tbody>
            @forelse ($workouts as $workout)
                <tr>
                    <td><a href="{{ route('workouts.show', $workout['id']) }}">{{ $workout['name'] ?? 'Workout' }}</a></td>
                    <td>{{ $workout['kind'] }}</td>
                    <td class="nowrap">{{ $workout['started_at'] }}</td>
                    <td>{{ implode(', ', $workout['exercise_names']) }}</td>
                    <td>{{ $workout['set_count'] }}</td>
                    <td class="text-right">
                        <form class="inline-form" action="{{ route('workouts.destroy', $workout['id']) }}" method="POST">
                            @csrf
                            @method('DELETE')
                            <button class="danger-button" type="submit">Delete</button>
                        </form>
                    </td>
                </tr>
            @empty
                <tr>
                    <td colspan="6" class="muted">No workouts logged yet.</td>
                </tr>
            @endforelse
        </tbody>
    </table>
@endsection
