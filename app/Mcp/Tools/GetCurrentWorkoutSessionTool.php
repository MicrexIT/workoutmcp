<?php

namespace App\Mcp\Tools;

use App\Mcp\Tools\Concerns\ResolvesWorkoutUser;
use App\Services\WorkoutMemory\CurrentUserResolver;
use App\Services\WorkoutMemory\WorkoutSessionManager;
use Illuminate\Contracts\JsonSchema\JsonSchema;
use Laravel\Mcp\ResponseFactory;
use Laravel\Mcp\Server\Attributes\Description;
use Laravel\Mcp\Server\Attributes\Name;
use Laravel\Mcp\Server\Tool;
use Laravel\Mcp\Server\Tools\Annotations\IsDestructive;
use Laravel\Mcp\Server\Tools\Annotations\IsOpenWorld;
use Laravel\Mcp\Server\Tools\Annotations\IsReadOnly;

#[Name('get_current_workout_session')]
#[Description('Return the active in-progress workout, the latest completed workout, and targeting guidance. Use before deciding whether to append to a live session, update the recent last session, or log a separate completed past workout.')]
#[IsReadOnly]
#[IsDestructive(false)]
#[IsOpenWorld(false)]
class GetCurrentWorkoutSessionTool extends Tool
{
    use ResolvesWorkoutUser;

    public function handle(CurrentUserResolver $users, WorkoutSessionManager $sessions): ResponseFactory
    {
        return $this->structured($sessions->current($this->currentUser($users)), 'Current workout session context loaded.');
    }

    public function schema(JsonSchema $schema): array
    {
        return [];
    }

    public function outputSchema(JsonSchema $schema): array
    {
        return $this->baseOutputSchema($schema, [
            'active_session' => $this->workoutSessionSchema($schema)->required()->nullable(),
            'active_session_is_stale' => $schema->boolean()->required(),
            'latest_completed_session' => $this->workoutSessionSchema($schema)->required()->nullable(),
            'append_target_guidance' => $schema->object([
                'current_live_workout' => $schema->string()->required(),
                'just_logged_or_last_session' => $schema->string()->required(),
                'past_completed_workout' => $schema->string()->required(),
                'stale_or_wrongly_completed' => $schema->string()->required(),
            ])->required(),
        ]);
    }
}
