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
#[Description('Correct a logged workout in place with ordered operations: update_session (rename, re-date to when it really happened, notes, effort, bodyweight); add_exercise (a genuinely missing entry; same shape and server-side resolution as log_workout entries — never a corrected duplicate of an existing entry); update_exercise (THE way to fix a wrong entry — it remaps in place and keeps the sets: send the user\'s corrected wording as raw_phrase — resolved server-side, flagged auto-create as last resort, never refused — or an explicit exercise_id, which is authoritative and applied even when the entry\'s old wording resolves elsewhere; set remember_phrase=true when the user confirms the wording always means that exercise; an unchanged correction is reported with a hint); add_set / update_set / remove_set; remove_exercise (delete an entry that should not exist at all, e.g. a duplicate — to swap a wrong exercise use update_exercise instead); reopen_session (set a wrongly-completed workout back to in_progress so live appends target it again); merge_workout (absorb another workout that was really the same session into this one, deleting the emptied source). remove_exercise, remove_set, and merge_workout require user_confirmed_destructive_change=true; the user\'s own request to delete, replace, or merge counts as that confirmation — ask first only for removals they did not ask for. A refused call applies none of its operations. Fetch workout_exercise_id / workout_set_id values from get_workout or the logging response first; field-level details are described in the operations schema.')]
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
                'exercise_id' => $schema->integer()->nullable()->description('add_exercise: optional resolution hint. update_exercise: remap the entry to this exercise — authoritative, applied even when the entry\'s old wording resolves elsewhere (a disagreement is reported in correction_phrase_conflicts).'),
                'remember_phrase' => $schema->boolean()->default(false)->description('update_exercise remap: also store the entry\'s raw_phrase as phrase memory for the corrected exercise so future logs resolve it directly.'),
                'raw_phrase' => $schema->string()->nullable()->description('add_exercise: the user\'s wording, resolved server-side exactly like log_workout entries. update_exercise: the corrected wording for the entry, resolved the same way to fix a wrong exercise mapping.'),
                'resolution_id' => $schema->string()->nullable()->description('add_exercise/update_exercise: optional evidence id from resolve_exercise_mentions.'),
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
            'user_confirmed_destructive_change' => $schema->boolean()->default(false)->description('Required true for remove_exercise, remove_set, and merge_workout. The user\'s own request to delete, replace, or merge counts as confirmation — set true and proceed without re-asking. Ask first only when the removal is your idea rather than something the user asked for.'),
        ];
    }
}
