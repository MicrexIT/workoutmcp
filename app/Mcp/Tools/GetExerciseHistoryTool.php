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

#[Name('get_exercise_history')]
#[Description('Return recent and aggregate history for one exercise, optionally scoped to a bucket variant label.')]
#[IsReadOnly]
class GetExerciseHistoryTool extends Tool
{
    use ResolvesWorkoutUser;

    public function handle(Request $request, CurrentUserResolver $users, TrainingSummaryService $summaries): ResponseFactory
    {
        $validated = $request->validate([
            'exercise_id' => ['required', 'integer'],
            'variant_label' => ['sometimes', 'nullable', 'string'],
            'since' => ['sometimes', 'nullable', 'string'],
            'limit' => ['sometimes', 'integer', 'min:1', 'max:50'],
        ]);

        return $this->structured([
            'history' => $summaries->exerciseHistory(
                $this->currentUser($users),
                (int) $validated['exercise_id'],
                $validated['variant_label'] ?? null,
                $validated['since'] ?? null,
                $validated['limit'] ?? 10,
            ),
        ], 'Exercise history loaded.');
    }

    public function schema(JsonSchema $schema): array
    {
        return [
            'exercise_id' => $schema->integer()->required(),
            'variant_label' => $schema->string()->nullable(),
            'since' => $schema->string()->nullable(),
            'limit' => $schema->integer()->default(10),
        ];
    }
}
