<?php

namespace App\Mcp\Tools;

use App\Mcp\Tools\Concerns\ResolvesWorkoutUser;
use App\Services\WorkoutMemory\CurrentUserResolver;
use App\Services\WorkoutMemory\WorkoutUpdater;
use Illuminate\Contracts\JsonSchema\JsonSchema;
use Laravel\Mcp\Request;
use Laravel\Mcp\ResponseFactory;
use Laravel\Mcp\Server\Attributes\Description;
use Laravel\Mcp\Server\Attributes\Name;
use Laravel\Mcp\Server\Tool;
use Laravel\Mcp\Server\Tools\Annotations\IsDestructive;
use Laravel\Mcp\Server\Tools\Annotations\IsIdempotent;
use Laravel\Mcp\Server\Tools\Annotations\IsOpenWorld;
use Laravel\Mcp\Server\Tools\Annotations\IsReadOnly;

#[Name('delete_workout')]
#[Description('Soft-delete a mistakenly logged workout. Requires user_confirmed=true.')]
#[IsReadOnly(false)]
#[IsDestructive]
#[IsOpenWorld(false)]
#[IsIdempotent]
class DeleteWorkoutTool extends Tool
{
    use ResolvesWorkoutUser;

    public function handle(Request $request, CurrentUserResolver $users, WorkoutUpdater $updater): ResponseFactory
    {
        $validated = $request->validate([
            'workout_id' => ['required', 'integer'],
            'reason' => ['sometimes', 'nullable', 'string'],
            'user_confirmed' => ['required', 'boolean'],
        ]);

        return $this->structured($updater->delete(
            $this->currentUser($users),
            (int) $validated['workout_id'],
            $validated['reason'] ?? null,
            (bool) $validated['user_confirmed'],
        ), 'Workout delete handled.');
    }

    public function schema(JsonSchema $schema): array
    {
        return [
            'workout_id' => $schema->integer()->required(),
            'reason' => $schema->string()->nullable(),
            'user_confirmed' => $schema->boolean()->required()->description('Must be true, and only after the user explicitly confirmed the deletion.'),
        ];
    }
}
