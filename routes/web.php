<?php

use App\Http\Controllers\Auth\AuthenticatedSessionController;
use App\Http\Controllers\Auth\RegisteredUserController;
use App\Models\Exercise;
use App\Models\WorkoutSession;
use App\Models\WorkoutShare;
use App\Services\WorkoutMemory\CurrentUserResolver;
use App\Services\WorkoutMemory\TrainingSummaryService;
use App\Services\WorkoutMemory\WorkoutShareService;
use App\Services\WorkoutMemory\WorkoutUpdater;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Route;

Route::get('/', function () {
    $publicUrl = rtrim((string) config('workout_memory.oauth.public_url'), '/');
    $mcpUrl = $publicUrl.'/mcp/workout-memory';

    $setupPrompt = 'I want to connect an MCP server called "Workout Memory" to this app. '
        ."MCP endpoint: {$mcpUrl} (streamable HTTP with OAuth sign-in). "
        .'Guide me through adding it as a custom connector in this app, step by step, using the exact menu names I will see here. '
        ."If I need an account first, send me to {$publicUrl}. "
        .'Once it is connected, ask me what I trained today and log my first workout.';

    return view('landing', [
        'mcpUrl' => $mcpUrl,
        'registrationOpen' => (bool) config('workout_memory.registration.enabled'),
        'setupPrompt' => $setupPrompt,
        'chatGptPromptUrl' => 'https://chatgpt.com/?q='.rawurlencode($setupPrompt),
        'claudePromptUrl' => 'https://claude.ai/new?q='.rawurlencode($setupPrompt),
    ]);
})->name('landing');

Route::get('/w/{slug}', function (string $slug, TrainingSummaryService $summaries, WorkoutShareService $shares) {
    $share = WorkoutShare::query()->where('slug', $slug)->active()->first();

    abort_if($share === null, 404);

    $session = $share->workoutSession;

    abort_if($session === null || $session->status !== 'completed', 404);

    $workout = $summaries->workout($session);

    return view('share.workout', [
        'workout' => $workout,
        'lines' => collect($workout['exercises'])->map(fn (array $exercise): array => [
            'name' => $exercise['name'],
            'variant' => $exercise['variant_label'],
            'sets' => $shares->compactSetsLine($exercise),
        ]),
        'dateLabel' => $workout['started_at'] ? Carbon::parse($workout['started_at'])->format('j M Y') : null,
        'ownerFirstName' => str($share->user->name)->trim()->explode(' ')->first(),
    ]);
})->where('slug', '[a-zA-Z0-9]+')->name('workouts.shared');

Route::get('/llms.txt', function () {
    $publicUrl = rtrim((string) config('workout_memory.oauth.public_url'), '/');

    return response()
        ->view('llms-txt', [
            'publicUrl' => $publicUrl,
            'mcpUrl' => $publicUrl.'/mcp/workout-memory',
        ])
        ->header('Content-Type', 'text/plain; charset=UTF-8');
})->name('llms');

Route::get('/docs', function () {
    $publicUrl = rtrim((string) config('workout_memory.oauth.public_url'), '/');

    return view('docs', [
        'publicUrl' => $publicUrl,
        'mcpUrl' => $publicUrl.'/mcp/workout-memory',
        'supportEmail' => (string) config('workout_memory.support.email'),
    ]);
})->name('docs');

Route::get('/privacy', function () {
    return view('privacy', [
        'companyName' => (string) config('workout_memory.company.name'),
        'supportEmail' => (string) config('workout_memory.support.email'),
    ]);
})->name('privacy');

Route::get('/support', function () {
    $publicUrl = rtrim((string) config('workout_memory.oauth.public_url'), '/');

    return view('support', [
        'publicUrl' => $publicUrl,
        'mcpUrl' => $publicUrl.'/mcp/workout-memory',
        'supportEmail' => (string) config('workout_memory.support.email'),
    ]);
})->name('support');

Route::middleware('guest')->group(function (): void {
    Route::get('/register', [RegisteredUserController::class, 'create'])->name('register');
    Route::post('/register', [RegisteredUserController::class, 'store'])
        ->middleware('throttle:5,1')
        ->name('register.store');

    Route::get('/login', [AuthenticatedSessionController::class, 'create'])->name('login');
    Route::post('/login', [AuthenticatedSessionController::class, 'store'])
        ->middleware('throttle:10,1')
        ->name('login.store');
});

Route::post('/logout', [AuthenticatedSessionController::class, 'destroy'])
    ->middleware('auth')
    ->name('logout');

Route::middleware('auth')->group(function (): void {
    Route::get('/dashboard', function (CurrentUserResolver $users) {
        $user = $users->user();

        return view('debug.home', [
            'user' => $user,
            'exerciseCount' => Exercise::query()
                ->where(fn ($builder) => $builder->whereNull('user_id')->orWhere('user_id', $user->id))
                ->count(),
            'workoutCount' => WorkoutSession::query()
                ->where('user_id', $user->id)
                ->where('status', '!=', 'deleted')
                ->count(),
        ]);
    })->name('home');

    Route::get('/exercises', function (CurrentUserResolver $users) {
        $user = $users->user();

        return view('debug.exercises', [
            'exercises' => Exercise::query()
                ->where(fn ($builder) => $builder->whereNull('user_id')->orWhere('user_id', $user->id))
                ->with(['aliases', 'parent'])
                ->orderBy('category')
                ->orderBy('name')
                ->paginate(250),
        ]);
    })->name('exercises.index');

    Route::get('/workouts', function (CurrentUserResolver $users, TrainingSummaryService $summaries) {
        return view('debug.workouts', [
            'workouts' => $summaries->recentWorkouts($users->user(), 50),
        ]);
    })->name('workouts.index');

    Route::get('/workouts/{workoutSession}', function (WorkoutSession $workoutSession, CurrentUserResolver $users, TrainingSummaryService $summaries, WorkoutShareService $shares) {
        abort_unless($workoutSession->user_id === $users->user()->id, 404);

        return view('debug.workout', [
            'workout' => $summaries->workout($workoutSession),
            'share' => $shares->activeShareFor($workoutSession),
        ]);
    })->name('workouts.show');

    Route::post('/workouts/{workoutSession}/share', function (WorkoutSession $workoutSession, CurrentUserResolver $users, WorkoutShareService $shares) {
        $user = $users->user();

        abort_unless($workoutSession->user_id === $user->id, 404);

        $result = $shares->share($user, $workoutSession->id);

        if ($result['refused']) {
            return back()->with('error', $result['refusal_reason']);
        }

        return back()->with('status', 'Share link created.');
    })->name('workouts.share.store');

    Route::delete('/workouts/{workoutSession}/share', function (WorkoutSession $workoutSession, CurrentUserResolver $users, WorkoutShareService $shares) {
        abort_unless($workoutSession->user_id === $users->user()->id, 404);

        $shares->revoke($workoutSession);

        return back()->with('status', 'Share link revoked.');
    })->name('workouts.share.destroy');

    Route::delete('/workouts/{workoutSession}', function (WorkoutSession $workoutSession, CurrentUserResolver $users, WorkoutUpdater $updater) {
        $user = $users->user();

        abort_unless($workoutSession->user_id === $user->id, 404);

        $result = $updater->delete($user, $workoutSession->id, 'Deleted from debug UI.', true);

        if ($result['refused']) {
            return back()->with('error', $result['refusal_reason']);
        }

        return redirect()
            ->route('workouts.index')
            ->with('status', 'Workout deleted.');
    })->name('workouts.destroy');
});
