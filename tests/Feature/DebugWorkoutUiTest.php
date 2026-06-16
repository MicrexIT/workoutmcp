<?php

namespace Tests\Feature;

use App\Models\Exercise;
use App\Models\User;
use App\Models\WorkoutSession;
use App\Services\WorkoutMemory\CurrentUserResolver;
use App\Services\WorkoutMemory\WorkoutLogger;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class DebugWorkoutUiTest extends TestCase
{
    use RefreshDatabase;

    private User $user;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed();
        $this->user = app(CurrentUserResolver::class)->user();
        $this->user->markEmailAsVerified();
        $this->actingAs($this->user);
    }

    public function test_workouts_index_includes_delete_form(): void
    {
        $workoutId = $this->logWorkout('Ring dip cleanup');

        $this->get(route('workouts.index'))
            ->assertOk()
            ->assertSee('Ring dip cleanup')
            ->assertSee('Delete')
            ->assertSee(route('workouts.destroy', $workoutId), false)
            ->assertSee('_method', false)
            ->assertSee('DELETE', false);
    }

    public function test_workout_can_be_deleted_from_ui(): void
    {
        $workoutId = $this->logWorkout('Mistaken workout');

        $this->delete(route('workouts.destroy', $workoutId))
            ->assertRedirect(route('workouts.index'))
            ->assertSessionHas('status', 'Workout deleted.');

        $workout = WorkoutSession::withTrashed()->findOrFail($workoutId);

        $this->assertSame('deleted', $workout->status);
        $this->assertNotNull($workout->deleted_at);
        $this->assertDatabaseHas('workout_change_events', [
            'workout_session_id' => $workoutId,
            'event_type' => 'deleted',
            'reason' => 'Deleted from debug UI.',
        ]);

        $this->get(route('workouts.index'))
            ->assertOk()
            ->assertDontSee('Mistaken workout');
    }

    public function test_workout_detail_formats_duration_seconds_for_display(): void
    {
        $exercise = Exercise::query()->where('name', 'Plank')->firstOrFail();
        $logged = app(WorkoutLogger::class)->log($this->user, [
            'name' => 'Timed holds',
            'raw_input' => 'plank for 45 seconds, 65 seconds, and 3661 seconds',
            'exercises' => [[
                'exercise_id' => $exercise->id,
                'raw_phrase' => 'plank',
                'sets' => [
                    ['duration_seconds' => 45],
                    ['duration_seconds' => 65],
                    ['duration_seconds' => 3661],
                ],
            ]],
        ]);

        $this->assertFalse($logged['refused']);

        $this->get(route('workouts.show', $logged['saved_session']['id']))
            ->assertOk()
            ->assertSee('45 seconds')
            ->assertSee('1 minute 5 seconds')
            ->assertSee('1 hour 1 minute 1 second');
    }

    private function logWorkout(string $name): int
    {
        $exercise = Exercise::query()->where('name', 'Ring Dip')->firstOrFail();
        $logged = app(WorkoutLogger::class)->log($this->user, [
            'name' => $name,
            'raw_input' => 'ring dips 1x8',
            'exercises' => [[
                'exercise_id' => $exercise->id,
                'raw_phrase' => 'ring dips',
                'resolution_type' => 'alias',
                'sets' => [['reps' => 8]],
            ]],
        ]);

        $this->assertFalse($logged['refused']);

        return $logged['saved_session']['id'];
    }
}
