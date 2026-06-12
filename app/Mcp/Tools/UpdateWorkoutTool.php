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
            'workout_id' => $schema->integer()->required()->description('The workout to correct. Find ids via get_workout, list_recent_workouts, or the logging response.'),
            'operations' => $schema->array()->items($schema->object([
                'type' => $schema->string()->enum(['update_session', 'add_exercise', 'update_exercise', 'remove_exercise', 'add_set', 'update_set', 'remove_set', 'reopen_session', 'merge_workout'])->required(),
                'workout_exercise_id' => $schema->integer()->nullable()->description('Target entry for update_exercise, remove_exercise, and add_set. Ids come from get_workout or the logging response (exercises[].id).'),
                'workout_set_id' => $schema->integer()->nullable()->description('Target set for update_set and remove_set (exercises[].sets[].id).'),
                'source_workout_id' => $schema->integer()->nullable()->description('merge_workout: the workout whose exercises are absorbed into this one; the emptied source is then deleted.'),
                'exercise_id' => $schema->integer()->nullable()->description('add_exercise: optional resolution hint. update_exercise: remap the entry to this exercise.'),
                'remember_phrase' => $schema->boolean()->default(false)->description('update_exercise remap: also store the entry\'s raw_phrase as phrase memory for the corrected exercise so future logs resolve it directly.'),
                'raw_phrase' => $schema->string()->nullable()->description('add_exercise: the user\'s wording, resolved server-side exactly like log_workout entries.'),
                'resolution_id' => $schema->string()->nullable()->description('add_exercise: optional evidence id from resolve_exercise_mentions.'),
                'sets' => $schema->array()->items($schema->object([
                    'set_number' => $schema->integer()->nullable(),
                    'reps' => $schema->integer()->nullable(),
                    'load_value' => $schema->number()->nullable(),
                    'load_unit' => $schema->string()->enum(['kg', 'lb'])->nullable(),
                    'load_type' => $schema->string()->enum(['implement', 'external', 'assistance', 'bodyweight', 'unknown'])->nullable(),
                    'duration_seconds' => $schema->integer()->nullable(),
                    'distance_value' => $schema->number()->nullable(),
                    'distance_unit' => $schema->string()->enum(['m', 'km', 'mi'])->nullable(),
                    'rpe' => $schema->number()->nullable(),
                    'rir' => $schema->number()->nullable(),
                    'side' => $schema->string()->enum(['left', 'right', 'both', 'alternating'])->nullable(),
                    'success' => $schema->boolean()->nullable(),
                    'quality_rating' => $schema->integer()->nullable(),
                    'is_warmup' => $schema->boolean()->default(false),
                    'notes' => $schema->string()->nullable(),
                ]))->description('add_exercise only: the entry\'s sets, same shape as log_workout sets.'),
                'set_number' => $schema->integer()->nullable()->description('add_set/update_set.'),
                'reps' => $schema->integer()->nullable()->description('add_set/update_set.'),
                'load_value' => $schema->number()->nullable()->description('add_set/update_set.'),
                'load_unit' => $schema->string()->enum(['kg', 'lb'])->nullable()->description('add_set/update_set.'),
                'load_type' => $schema->string()->enum(['implement', 'external', 'assistance', 'bodyweight', 'unknown'])->nullable()->description('add_set/update_set.'),
                'duration_seconds' => $schema->integer()->nullable()->description('add_set/update_set.'),
                'distance_value' => $schema->number()->nullable()->description('add_set/update_set.'),
                'distance_unit' => $schema->string()->enum(['m', 'km', 'mi'])->nullable()->description('add_set/update_set.'),
                'rpe' => $schema->number()->nullable()->description('add_set/update_set.'),
                'rir' => $schema->number()->nullable()->description('add_set/update_set.'),
                'side' => $schema->string()->enum(['left', 'right', 'both', 'alternating'])->nullable()->description('add_set/update_set.'),
                'is_warmup' => $schema->boolean()->nullable()->description('add_set/update_set.'),
                'name' => $schema->string()->nullable()->description('update_session.'),
                'kind' => $schema->string()->nullable()->description('update_session.'),
                'perceived_effort' => $schema->integer()->nullable()->description('update_session: 1-10 session effort.'),
                'occurred_at' => $schema->string()->nullable()->description('update_session: move the workout to when it really happened (ISO 8601).'),
                'bodyweight_value' => $schema->number()->nullable()->description('update_session.'),
                'bodyweight_unit' => $schema->string()->enum(['kg', 'lb'])->nullable()->description('update_session.'),
                'sort_order' => $schema->integer()->nullable()->description('add_exercise: optional position within the workout.'),
                'variant_label' => $schema->string()->nullable()->description('add_exercise/update_exercise.'),
                'variant_description' => $schema->string()->nullable()->description('add_exercise/update_exercise.'),
                'prescription' => $schema->string()->nullable()->description('add_exercise.'),
                'notes' => $schema->string()->nullable()->description('update_session/add_exercise/update_exercise/add_set/update_set.'),
            ]))->required()->description('Operations are applied in order within one transaction.'),
            'reason' => $schema->string()->nullable()->description('Why the workout is being corrected; stored on the change event.'),
            'user_confirmed_destructive_change' => $schema->boolean()->default(false)->description('Required true for remove_exercise, remove_set, and merge_workout, only after the user explicitly confirmed.'),
        ];
    }
}
