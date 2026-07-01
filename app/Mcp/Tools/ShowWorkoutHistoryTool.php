<?php

namespace App\Mcp\Tools;

use App\Mcp\Resources\WorkoutHistoryApp;
use App\Mcp\Tools\Concerns\BuildsWorkoutOutputSchemas;
use Illuminate\Contracts\JsonSchema\JsonSchema;
use Laravel\Mcp\Request;
use Laravel\Mcp\Response;
use Laravel\Mcp\ResponseFactory;
use Laravel\Mcp\Server\Attributes\Description;
use Laravel\Mcp\Server\Attributes\Name;
use Laravel\Mcp\Server\Attributes\RendersApp;
use Laravel\Mcp\Server\Tool;
use Laravel\Mcp\Server\Tools\Annotations\IsDestructive;
use Laravel\Mcp\Server\Tools\Annotations\IsOpenWorld;
use Laravel\Mcp\Server\Tools\Annotations\IsReadOnly;

#[Name('show_workout_history')]
#[Description('Open a read-only workout history interface with paginated session summaries and selectable workout details. Use this when the user wants to browse, inspect, or choose from past training sessions.')]
#[RendersApp(resource: WorkoutHistoryApp::class)]
#[IsReadOnly]
#[IsDestructive(false)]
#[IsOpenWorld(false)]
class ShowWorkoutHistoryTool extends Tool
{
    use BuildsWorkoutOutputSchemas;

    public function handle(Request $request): ResponseFactory
    {
        $validated = $request->validate([
            'limit' => ['sometimes', 'integer', 'min:1', 'max:50'],
            'cursor' => ['sometimes', 'nullable', 'string'],
            'since' => ['sometimes', 'nullable', 'string'],
            'kind' => ['sometimes', 'nullable', 'string'],
            'selected_workout_id' => ['sometimes', 'nullable', 'integer'],
        ]);

        return Response::structured([
            'ok' => true,
            'message' => 'Workout history app loaded.',
            'initial_query' => [
                'limit' => $validated['limit'] ?? 20,
                'cursor' => $validated['cursor'] ?? null,
                'since' => $validated['since'] ?? null,
                'kind' => $validated['kind'] ?? null,
                'selected_workout_id' => $validated['selected_workout_id'] ?? null,
            ],
        ]);
    }

    public function schema(JsonSchema $schema): array
    {
        return [
            'limit' => $schema->integer()->default(20)->description('Number of sessions to show per page, from 1 to 50.'),
            'cursor' => $schema->string()->nullable()->description('Optional cursor returned by list_recent_workouts pagination.'),
            'since' => $schema->string()->nullable()->description('Optional ISO 8601 lower bound for workout started_at.'),
            'kind' => $schema->string()->nullable()->description('Optional workout kind filter such as strength, mobility, conditioning, endurance, or mixed.'),
            'selected_workout_id' => $schema->integer()->nullable()->description('Optional workout id to open in the detail pane if it is visible or loadable.'),
        ];
    }

    public function outputSchema(JsonSchema $schema): array
    {
        return $this->baseOutputSchema($schema, [
            'initial_query' => $schema->object([
                'limit' => $schema->integer()->required(),
                'cursor' => $schema->string()->required()->nullable(),
                'since' => $schema->string()->required()->nullable(),
                'kind' => $schema->string()->required()->nullable(),
                'selected_workout_id' => $schema->integer()->required()->nullable(),
            ])->required(),
        ]);
    }
}
