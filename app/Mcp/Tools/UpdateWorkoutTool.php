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

#[Name('update_workout')]
#[Description('Correct a logged workout with explicit operations. Removing exercises or sets requires user_confirmed_destructive_change=true. add_exercise operations accept the same entry shape as log_workout exercises (raw_phrase plus optional exercise_id/resolution_id) and resolve server-side, auto-creating a flagged exercise as a last resort; ids that contradict the raw_phrase are ignored and reported in ignored_exercise_hints. update_exercise can remap an entry to the right exercise via exercise_id (pass remember_phrase=true when the user confirms the wording should always mean that exercise). update_session with occurred_at moves the workout to the date it really happened. A reopen_session operation sets a wrongly-completed workout back to in_progress (requires no other in-progress session) so live appends target it again. A merge_workout operation with source_workout_id absorbs that workout\'s exercises into this one and deletes the emptied source — use it when one real session was logged as two workouts; requires user_confirmed_destructive_change=true.')]
#[IsIdempotent]
#[IsDestructive]
class UpdateWorkoutTool extends Tool
{
    use ResolvesWorkoutUser;

    public function handle(Request $request, CurrentUserResolver $users, WorkoutUpdater $updater): ResponseFactory
    {
        $validated = $request->validate([
            'workout_id' => ['required', 'integer'],
            'operations' => ['required', 'array', 'min:1'],
            'operations.*.type' => ['required', 'string'],
            'reason' => ['sometimes', 'nullable', 'string'],
            'user_confirmed_destructive_change' => ['sometimes', 'boolean'],
        ]);

        $validated['operations'] = $request->get('operations', []);

        return $this->structured($updater->update($this->currentUser($users), $validated), 'Workout update handled.');
    }

    public function schema(JsonSchema $schema): array
    {
        return [
            'workout_id' => $schema->integer()->required(),
            'operations' => $schema->array()->items($schema->object([
                'type' => $schema->string()->required(),
            ]))->required(),
            'reason' => $schema->string()->nullable(),
            'user_confirmed_destructive_change' => $schema->boolean()->default(false),
        ];
    }
}
