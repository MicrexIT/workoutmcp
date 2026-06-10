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
use Laravel\Mcp\Server\Tools\Annotations\IsReadOnly;

#[Name('get_current_workout_session')]
#[Description('Return the active in-progress workout, the latest completed workout, and targeting guidance. Use before deciding whether to append to a live session, update the recent last session, or log a separate completed past workout.')]
#[IsReadOnly]
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
}
