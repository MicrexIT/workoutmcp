<?php

namespace Tests\Feature;

use App\Mcp\Servers\WorkoutMemoryServer;
use App\Mcp\Tools\AppendWorkoutExerciseTool;
use App\Mcp\Tools\FinishWorkoutSessionTool;
use App\Mcp\Tools\LogWorkoutTool;
use App\Mcp\Tools\ShareWorkoutTool;
use App\Mcp\Tools\StartWorkoutSessionTool;
use App\Models\Exercise;
use App\Models\User;
use App\Models\WorkoutSession;
use App\Models\WorkoutShare;
use App\Services\WorkoutMemory\CurrentUserResolver;
use App\Services\WorkoutMemory\WorkoutLogger;
use App\Services\WorkoutMemory\WorkoutShareService;
use App\Services\WorkoutMemory\WorkoutUpdater;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Testing\Fluent\AssertableJson;
use Tests\TestCase;

class WorkoutShareTest extends TestCase
{
    use RefreshDatabase;

    private User $user;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed();
        $this->user = app(CurrentUserResolver::class)->user();
        $this->user->markEmailAsVerified();
    }

    public function test_share_workout_tool_creates_link_for_latest_completed_workout(): void
    {
        $this->logWorkout('Push day');

        WorkoutMemoryServer::tool(ShareWorkoutTool::class, [])
            ->assertStructuredContent(fn (AssertableJson $json) => $json
                ->where('ok', true)
                ->where('created', true)
                ->where('shared_workout.name', 'Push day')
                ->whereType('share_url', 'string')
                ->whereType('share_text', 'string')
                ->etc());

        $this->assertDatabaseCount('workout_shares', 1);

        $share = WorkoutShare::query()->firstOrFail();

        $this->assertStringContainsString('/w/'.$share->slug, $share->publicUrl());
        $this->assertNull($share->revoked_at);
    }

    public function test_share_workout_tool_reuses_the_active_link(): void
    {
        $workoutId = $this->logWorkout('Pull day');

        WorkoutMemoryServer::tool(ShareWorkoutTool::class, ['workout_session_id' => $workoutId])
            ->assertStructuredContent(fn (AssertableJson $json) => $json->where('created', true)->etc());

        WorkoutMemoryServer::tool(ShareWorkoutTool::class, ['workout_session_id' => $workoutId])
            ->assertStructuredContent(fn (AssertableJson $json) => $json
                ->where('ok', true)
                ->where('created', false)
                ->etc());

        $this->assertDatabaseCount('workout_shares', 1);
    }

    public function test_share_workout_tool_refuses_in_progress_sessions(): void
    {
        $session = WorkoutSession::factory()->create([
            'user_id' => $this->user->id,
            'status' => 'in_progress',
            'started_at' => now(),
            'completed_at' => null,
        ]);

        WorkoutMemoryServer::tool(ShareWorkoutTool::class, ['workout_session_id' => $session->id])
            ->assertStructuredContent(fn (AssertableJson $json) => $json
                ->where('ok', false)
                ->where('refusal_reason', 'Only completed workouts can be shared.')
                ->etc());

        $this->assertDatabaseCount('workout_shares', 0);
    }

    public function test_share_workout_tool_refuses_when_nothing_is_completed(): void
    {
        WorkoutMemoryServer::tool(ShareWorkoutTool::class, [])
            ->assertStructuredContent(fn (AssertableJson $json) => $json
                ->where('ok', false)
                ->where('refusal_reason', 'No completed workout to share yet.')
                ->etc());
    }

    public function test_log_workout_offers_sharing_but_session_tools_do_not(): void
    {
        WorkoutMemoryServer::tool(LogWorkoutTool::class, [
            'name' => 'Logged workout',
            'raw_input' => 'ring dips 1x8',
            'exercises' => [[
                'raw_phrase' => 'ring dips',
                'sets' => [['reps' => 8]],
            ]],
        ])->assertStructuredContent(fn (AssertableJson $json) => $json
            ->where('ok', true)
            ->where('sharing.share_available', true)
            ->etc());

        WorkoutMemoryServer::tool(StartWorkoutSessionTool::class, [
            'idempotency_key' => 'share-test:start',
        ])->assertStructuredContent(fn (AssertableJson $json) => $json
            ->where('ok', true)
            ->missing('sharing')
            ->etc());

        WorkoutMemoryServer::tool(AppendWorkoutExerciseTool::class, [
            'idempotency_key' => 'share-test:append',
            'exercise' => [
                'raw_phrase' => 'ring dips',
                'sets' => [['reps' => 6]],
            ],
        ])->assertStructuredContent(fn (AssertableJson $json) => $json
            ->where('ok', true)
            ->missing('sharing')
            ->etc());

        WorkoutMemoryServer::tool(FinishWorkoutSessionTool::class, [
            'idempotency_key' => 'share-test:finish',
        ])->assertStructuredContent(fn (AssertableJson $json) => $json
            ->where('ok', true)
            ->where('sharing.share_available', true)
            ->etc());
    }

    public function test_public_share_page_renders_completed_workout(): void
    {
        $workoutId = $this->logWorkout('Shared push day');
        $share = $this->shareWorkout($workoutId);

        $this->get('/w/'.$share->slug)
            ->assertOk()
            ->assertSee('Shared push day')
            ->assertSee('Ring Dip')
            ->assertSee('noindex', false)
            ->assertSee('Get Workout Memory')
            ->assertDontSee($this->user->email);
    }

    public function test_public_share_page_is_gone_after_revoke_or_workout_deletion(): void
    {
        $workoutId = $this->logWorkout('Temporary workout');
        $share = $this->shareWorkout($workoutId);

        $this->get('/w/'.$share->slug)->assertOk();

        app(WorkoutShareService::class)->revoke(WorkoutSession::query()->findOrFail($workoutId));
        $this->get('/w/'.$share->slug)->assertNotFound();

        $second = $this->shareWorkout($workoutId);
        $this->get('/w/'.$second->slug)->assertOk();
        $this->assertNotSame($share->slug, $second->slug);

        app(WorkoutUpdater::class)->delete($this->user, $workoutId, 'Cleanup.', true);
        $this->get('/w/'.$second->slug)->assertNotFound();

        $this->get('/w/unknownslug123')->assertNotFound();
    }

    public function test_share_can_be_managed_from_the_web_dashboard(): void
    {
        $workoutId = $this->logWorkout('Dashboard workout');

        $this->actingAs($this->user);

        $this->get(route('workouts.show', $workoutId))
            ->assertOk()
            ->assertSee('Create share link');

        $this->post(route('workouts.share.store', $workoutId))
            ->assertRedirect()
            ->assertSessionHas('status', 'Share link created.');

        $share = WorkoutShare::query()->firstOrFail();

        $this->get(route('workouts.show', $workoutId))
            ->assertOk()
            ->assertSee($share->publicUrl())
            ->assertSee('Revoke');

        $this->delete(route('workouts.share.destroy', $workoutId))
            ->assertRedirect()
            ->assertSessionHas('status', 'Share link revoked.');

        $this->assertNotNull($share->fresh()->revoked_at);
    }

    private function logWorkout(string $name): int
    {
        $exercise = Exercise::query()->where('name', 'Ring Dip')->firstOrFail();
        $logged = app(WorkoutLogger::class)->log($this->user, [
            'name' => $name,
            'raw_input' => 'ring dips 3x8 at 10kg',
            'exercises' => [[
                'exercise_id' => $exercise->id,
                'raw_phrase' => 'ring dips',
                'sets' => [
                    ['reps' => 8, 'load_value' => 10, 'load_unit' => 'kg'],
                    ['reps' => 8, 'load_value' => 10, 'load_unit' => 'kg'],
                    ['reps' => 8, 'load_value' => 10, 'load_unit' => 'kg'],
                ],
            ]],
        ]);

        $this->assertFalse($logged['refused']);

        return $logged['saved_session']['id'];
    }

    private function shareWorkout(int $workoutId): WorkoutShare
    {
        $result = app(WorkoutShareService::class)->share($this->user, $workoutId);

        $this->assertFalse($result['refused']);

        return WorkoutShare::query()->active()->where('workout_session_id', $workoutId)->latest('id')->firstOrFail();
    }
}
