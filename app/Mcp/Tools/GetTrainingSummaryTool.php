<?php

namespace App\Mcp\Tools;

use App\Mcp\Tools\Concerns\ResolvesWorkoutUser;
use App\Services\WorkoutMemory\CurrentUserResolver;
use App\Services\WorkoutMemory\TrainingSummaryService;
use App\Services\WorkoutMemory\WorkoutSessionManager;
use Illuminate\Contracts\JsonSchema\JsonSchema;
use Laravel\Mcp\Request;
use Laravel\Mcp\ResponseFactory;
use Laravel\Mcp\Server\Attributes\Description;
use Laravel\Mcp\Server\Attributes\Name;
use Laravel\Mcp\Server\Tool;
use Laravel\Mcp\Server\Tools\Annotations\IsDestructive;
use Laravel\Mcp\Server\Tools\Annotations\IsOpenWorld;
use Laravel\Mcp\Server\Tools\Annotations\IsReadOnly;

#[Name('get_training_summary')]
#[Description('Return useful recent training context for ChatGPT workout planning. Laravel summarizes; ChatGPT still proposes the workout.')]
#[IsReadOnly]
#[IsDestructive(false)]
#[IsOpenWorld(false)]
class GetTrainingSummaryTool extends Tool
{
    use ResolvesWorkoutUser;

    public function handle(Request $request, CurrentUserResolver $users, TrainingSummaryService $summaries, WorkoutSessionManager $sessions): ResponseFactory
    {
        $validated = $request->validate([
            'since' => ['sometimes', 'nullable', 'string'],
            'focus' => ['sometimes', 'nullable', 'string'],
            'include_buckets' => ['sometimes', 'boolean'],
        ]);

        return $this->structured([
            'stale_active_session' => $sessions->staleActiveSessionNotice($this->currentUser($users)),
            'training_summary' => $summaries->trainingSummary(
                $this->currentUser($users),
                $validated['since'] ?? null,
                $validated['focus'] ?? null,
                $validated['include_buckets'] ?? true,
            ),
        ], 'Training summary loaded.');
    }

    public function schema(JsonSchema $schema): array
    {
        return [
            'since' => $schema->string()->nullable(),
            'focus' => $schema->string()->nullable(),
            'include_buckets' => $schema->boolean()->default(true),
        ];
    }
}
