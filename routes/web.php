<?php

use App\Http\Controllers\Auth\AuthenticatedSessionController;
use App\Http\Controllers\Auth\RegisteredUserController;
use App\Models\Exercise;
use App\Models\WorkoutSession;
use App\Services\WorkoutMemory\CurrentUserResolver;
use App\Services\WorkoutMemory\TrainingSummaryService;
use App\Services\WorkoutMemory\WorkoutUpdater;
use Illuminate\Support\Facades\Route;

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
    Route::get('/', function (CurrentUserResolver $users) {
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

    Route::get('/workouts/{workoutSession}', function (WorkoutSession $workoutSession, CurrentUserResolver $users, TrainingSummaryService $summaries) {
        abort_unless($workoutSession->user_id === $users->user()->id, 404);

        return view('debug.workout', [
            'workout' => $summaries->workout($workoutSession),
        ]);
    })->name('workouts.show');

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
