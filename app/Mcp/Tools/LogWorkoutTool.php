<?php

namespace App\Mcp\Tools;

use App\Mcp\Tools\Concerns\ResolvesWorkoutUser;
use App\Services\WorkoutMemory\CurrentUserResolver;
use App\Services\WorkoutMemory\WorkoutLogger;
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

#[Name('log_workout')]
#[Description('Save a completed workout in one call. Always send each entry\'s raw_phrase; exercise_id and resolution_id are optional hints (copy log_entry_template from resolve_exercise_mentions verbatim when available). Never invent exercise_id values and never use an entry\'s position number as its id — when unsure, omit exercise_id and let the server resolve raw_phrase itself: it prefers existing exercises and buckets, and as a last resort creates a clearly-flagged exercise reported in auto_created_exercises — entries are never refused or dropped for resolution reasons. Hints that contradict the raw_phrase evidence are ignored and reported in ignored_exercise_hints. Surface assumed_matches, ignored_exercise_hints, and auto_created_exercises to the user and correct with remember_exercise_phrase or update_workout. If an in-progress session is open and this workout overlaps it, the server refuses with needs_confirmation: either append/finish the live session, or retry with user_confirmed_separate_workout=true.')]
#[IsReadOnly(false)]
#[IsDestructive(false)]
#[IsOpenWorld(false)]
#[IsIdempotent]
class LogWorkoutTool extends Tool
{
    use ResolvesWorkoutUser;

    public function handle(Request $request, CurrentUserResolver $users, WorkoutLogger $logger): ResponseFactory
    {
        $validated = $request->validate([
            'name' => ['sometimes', 'nullable', 'string', 'max:160'],
            'occurred_at' => ['sometimes', 'nullable', 'string'],
            'completed_at' => ['sometimes', 'nullable', 'string'],
            'timezone' => ['sometimes', 'nullable', 'string', 'max:80'],
            'kind' => ['sometimes', 'nullable', 'string', 'max:80'],
            'idempotency_key' => ['sometimes', 'nullable', 'string', 'max:255'],
            'source_message_id' => ['sometimes', 'nullable', 'string', 'max:255'],
            'raw_input' => ['required', 'string'],
            'notes' => ['sometimes', 'nullable', 'string'],
            'perceived_effort' => ['sometimes', 'nullable', 'integer', 'min:1', 'max:10'],
            'bodyweight_value' => ['sometimes', 'nullable', 'numeric'],
            'bodyweight_unit' => ['sometimes', 'nullable', 'in:kg,lb'],
            'user_confirmed_separate_workout' => ['sometimes', 'boolean'],
            'exercises' => ['required', 'array', 'min:1'],
            'exercises.*.exercise_id' => ['sometimes', 'nullable', 'integer'],
            'exercises.*.resolution_id' => ['sometimes', 'nullable', 'string'],
            'exercises.*.raw_phrase' => ['nullable', 'string', 'max:255', 'required_without:exercises.*.exercise_id'],
            'exercises.*.resolution_type' => ['sometimes', 'nullable', 'string'],
            'exercises.*.variant_label' => ['sometimes', 'nullable', 'string', 'max:255'],
            'exercises.*.variant_description' => ['sometimes', 'nullable', 'string'],
            'exercises.*.prescription' => ['sometimes', 'nullable', 'string'],
            'exercises.*.notes' => ['sometimes', 'nullable', 'string'],
            'exercises.*.sets' => ['required', 'array', 'min:1'],
            'exercises.*.sets.*.set_number' => ['sometimes', 'nullable', 'integer', 'min:1'],
            'exercises.*.sets.*.reps' => ['sometimes', 'nullable', 'integer', 'min:0'],
            'exercises.*.sets.*.load_value' => ['sometimes', 'nullable', 'numeric'],
            'exercises.*.sets.*.load_unit' => ['sometimes', 'nullable', 'in:kg,lb'],
            'exercises.*.sets.*.load_type' => ['sometimes', 'nullable', 'in:implement,external,assistance,bodyweight,unknown'],
            'exercises.*.sets.*.duration_seconds' => ['sometimes', 'nullable', 'integer', 'min:0'],
            'exercises.*.sets.*.distance_value' => ['sometimes', 'nullable', 'numeric'],
            'exercises.*.sets.*.distance_unit' => ['sometimes', 'nullable', 'in:m,km,mi'],
            'exercises.*.sets.*.rpe' => ['sometimes', 'nullable', 'numeric', 'min:0', 'max:10'],
            'exercises.*.sets.*.rir' => ['sometimes', 'nullable', 'numeric', 'min:0', 'max:10'],
            'exercises.*.sets.*.side' => ['sometimes', 'nullable', 'in:left,right,both,alternating'],
            'exercises.*.sets.*.success' => ['sometimes', 'nullable', 'boolean'],
            'exercises.*.sets.*.quality_rating' => ['sometimes', 'nullable', 'integer', 'min:1', 'max:5'],
            'exercises.*.sets.*.is_warmup' => ['sometimes', 'boolean'],
            'exercises.*.sets.*.custom_metrics' => ['sometimes', 'nullable', 'array'],
            'exercises.*.sets.*.raw_set_text' => ['sometimes', 'nullable', 'string'],
            'exercises.*.sets.*.notes' => ['sometimes', 'nullable', 'string'],
        ], [
            'exercises.*.raw_phrase.required_without' => 'Each exercise entry needs a raw_phrase (the user\'s wording) or an exercise_id.',
        ]);

        $result = $logger->log($this->currentUser($users), $validated);

        if (($result['refused'] ?? true) === false) {
            $result['sharing'] = [
                'share_available' => true,
                'hint' => 'If the user might want to share this workout (a new best, a milestone), offer a public share link; call share_workout only when they say yes.',
            ];
        }

        return $this->structured($result, 'Workout log handled.');
    }

    public function schema(JsonSchema $schema): array
    {
        return [
            'name' => $schema->string()->nullable(),
            'occurred_at' => $schema->string()->nullable()->description('When the workout actually happened (ISO 8601). Send it whenever the user names a time other than now — "yesterday", "this morning", "last Tuesday" — so the workout is dated correctly. Defaults to now.'),
            'completed_at' => $schema->string()->nullable()->description('When the workout ended, if the user gave a duration or end time. Defaults to occurred_at.'),
            'timezone' => $schema->string()->nullable(),
            'kind' => $schema->string()->nullable(),
            'idempotency_key' => $schema->string()->nullable()->description('Stable key such as "<message_id>:log". Reuse on retry so the same message never logs twice.'),
            'source_message_id' => $schema->string()->nullable()->description('Platform message id; also deduplicates replays.'),
            'raw_input' => $schema->string()->required(),
            'notes' => $schema->string()->nullable(),
            'perceived_effort' => $schema->integer()->nullable()->description('1-10 session effort.'),
            'bodyweight_value' => $schema->number()->nullable(),
            'bodyweight_unit' => $schema->string()->enum(['kg', 'lb'])->nullable(),
            'user_confirmed_separate_workout' => $schema->boolean()->default(false)->description('Set true only after the user confirms this workout is separate from the currently open in-progress session.'),
            'exercises' => $schema->array()->items($schema->object([
                'raw_phrase' => $schema->string()->description('The user\'s wording for the exercise. Always send it so the server can resolve or correct catalog matches.')->required(),
                'exercise_id' => $schema->integer()->nullable()->description('Optional hint: a real catalog id previously returned by this server (search_exercises, resolve_exercise_mentions, or workout history). Never an invented number or the entry\'s position in this list. Omit when unsure — the server resolves raw_phrase itself, and ids that contradict the raw_phrase are ignored.'),
                'resolution_id' => $schema->string()->nullable()->description('Optional evidence id from resolve_exercise_mentions or search_exercises.'),
                'resolution_type' => $schema->string()->nullable()->description('Ignored; the server derives it. Kept for backward compatibility.'),
                'variant_label' => $schema->string()->nullable()->description('Short variant note, mainly for bucket exercises. Auto-filled from raw_phrase when missing.'),
                'variant_description' => $schema->string()->nullable(),
                'prescription' => $schema->string()->nullable(),
                'notes' => $schema->string()->nullable(),
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
                    'quality_rating' => $schema->integer()->nullable()->description('1-5 movement quality.'),
                    'is_warmup' => $schema->boolean()->default(false),
                    'custom_metrics' => $schema->object([])->nullable(),
                    'raw_set_text' => $schema->string()->nullable(),
                    'notes' => $schema->string()->nullable(),
                ]))->required(),
            ]))->required(),
        ];
    }
}
