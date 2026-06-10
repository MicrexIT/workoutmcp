@extends('debug.layout', ['title' => 'Exercises'])

@section('content')
    <h1>Exercises</h1>
    <table>
        <thead>
            <tr>
                <th>Name</th>
                <th>Category</th>
                <th>Granularity</th>
                <th>Tracking</th>
                <th>Aliases</th>
                <th>Parent</th>
            </tr>
        </thead>
        <tbody>
            @foreach ($exercises as $exercise)
                <tr>
                    <td>{{ $exercise->name }}</td>
                    <td>{{ $exercise->category }}</td>
                    <td>{{ $exercise->granularity }}</td>
                    <td>{{ $exercise->tracking_mode }}</td>
                    <td>{{ $exercise->aliases->pluck('alias')->join(', ') }}</td>
                    <td>{{ $exercise->parent?->name }}</td>
                </tr>
            @endforeach
        </tbody>
    </table>
    <div class="panel">{{ $exercises->links() }}</div>
@endsection
