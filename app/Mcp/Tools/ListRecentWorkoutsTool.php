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

#[Name('list_recent_workouts')]
#[Description('Return recent completed workout summaries for planning and recall. When stale_active_session is present, recap it to the user and ask whether to finish it.')]
#[IsReadOnly]
#[IsDestructive(false)]
#[IsOpenWorld(false)]
class ListRecentWorkoutsTool extends Tool
{
    use ResolvesWorkoutUser;

    public function handle(Request $request, CurrentUserResolver $users, TrainingSummaryService $summaries, WorkoutSessionManager $sessions): ResponseFactory
    {
        $validated = $request->validate([
            'limit' => ['sometimes', 'integer', 'min:1', 'max:50'],
            'since' => ['sometimes', 'nullable', 'string'],
            'kind' => ['sometimes', 'nullable', 'string'],
        ]);

        return $this->structured([
            'stale_active_session' => $sessions->staleActiveSessionNotice($this->currentUser($users)),
            'sessions' => $summaries->recentWorkouts(
                $this->currentUser($users),
                $validated['limit'] ?? 10,
                $validated['since'] ?? null,
                $validated['kind'] ?? null,
            ),
        ], 'Recent workouts loaded.');
    }

    public function schema(JsonSchema $schema): array
    {
        return [
            'limit' => $schema->integer()->default(10),
            'since' => $schema->string()->nullable(),
            'kind' => $schema->string()->nullable(),
        ];
    }
}
