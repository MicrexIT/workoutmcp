<?php

namespace App\Mcp\Tools;

use App\Mcp\Tools\Concerns\ResolvesWorkoutUser;
use App\Services\WorkoutMemory\CurrentUserResolver;
use App\Services\WorkoutMemory\TrainingSummaryService;
use Illuminate\Contracts\JsonSchema\JsonSchema;
use Laravel\Mcp\Request;
use Laravel\Mcp\ResponseFactory;
use Laravel\Mcp\Server\Attributes\Description;
use Laravel\Mcp\Server\Attributes\Name;
use Laravel\Mcp\Server\Tool;
use Laravel\Mcp\Server\Tools\Annotations\IsReadOnly;

#[Name('get_workout')]
#[Description('Return one full logged workout with exercises and sets. Use it to look up workout_exercise_id (exercises[].id) and workout_set_id (exercises[].sets[].id) values before update_workout operations.')]
#[IsReadOnly]
class GetWorkoutTool extends Tool
{
    use ResolvesWorkoutUser;

    public function handle(Request $request, CurrentUserResolver $users, TrainingSummaryService $summaries): ResponseFactory
    {
        $validated = $request->validate([
            'workout_id' => ['required', 'integer'],
        ]);

        return $this->structured([
            'workout' => $summaries->getWorkout($this->currentUser($users), (int) $validated['workout_id']),
        ], 'Workout loaded.');
    }

    public function schema(JsonSchema $schema): array
    {
        return [
            'workout_id' => $schema->integer()->required(),
        ];
    }
}
