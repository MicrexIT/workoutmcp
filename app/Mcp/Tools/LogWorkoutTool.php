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
use Laravel\Mcp\Server\Tools\Annotations\IsIdempotent;

#[Name('log_workout')]
#[Description('Save a completed workout in one call. Always send each entry\'s raw_phrase; exercise_id and resolution_id are optional hints (copy log_entry_template from resolve_exercise_mentions when available). The server resolves phrases itself: it prefers existing exercises and buckets, and as a last resort creates a clearly-flagged exercise reported in auto_created_exercises — entries are never refused or dropped for resolution reasons. Surface assumed_matches and auto_created_exercises to the user and correct with remember_exercise_phrase or update_workout.')]
#[IsIdempotent]
class LogWorkoutTool extends Tool
{
    use ResolvesWorkoutUser;

    public function handle(Request $request, CurrentUserResolver $users, WorkoutLogger $logger): ResponseFactory
    {
        $validated = $request->validate([
            'name' => ['sometimes', 'nullable', 'string', 'max:160'],
            'occurred_at' => ['sometimes', 'nullable', 'string'],
            'timezone' => ['sometimes', 'nullable', 'string', 'max:80'],
            'kind' => ['sometimes', 'nullable', 'string', 'max:80'],
            'idempotency_key' => ['sometimes', 'nullable', 'string', 'max:255'],
            'source_message_id' => ['sometimes', 'nullable', 'string', 'max:255'],
            'raw_input' => ['required', 'string'],
            'notes' => ['sometimes', 'nullable', 'string'],
            'perceived_effort' => ['sometimes', 'nullable', 'integer', 'min:1', 'max:10'],
            'bodyweight_value' => ['sometimes', 'nullable', 'numeric'],
            'bodyweight_unit' => ['sometimes', 'nullable', 'in:kg,lb'],
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

        return $this->structured($logger->log($this->currentUser($users), $validated), 'Workout log handled.');
    }

    public function schema(JsonSchema $schema): array
    {
        return [
            'name' => $schema->string()->nullable(),
            'occurred_at' => $schema->string()->nullable(),
            'timezone' => $schema->string()->nullable(),
            'kind' => $schema->string()->nullable(),
            'idempotency_key' => $schema->string()->nullable(),
            'source_message_id' => $schema->string()->nullable(),
            'raw_input' => $schema->string()->required(),
            'notes' => $schema->string()->nullable(),
            'perceived_effort' => $schema->integer()->nullable()->description('1-10 session effort.'),
            'bodyweight_value' => $schema->number()->nullable(),
            'bodyweight_unit' => $schema->string()->enum(['kg', 'lb'])->nullable(),
            'exercises' => $schema->array()->items($schema->object([
                'raw_phrase' => $schema->string()->description('The user\'s wording for the exercise. Always send it so the server can resolve or correct catalog matches.')->required(),
                'exercise_id' => $schema->integer()->nullable()->description('Optional hint. The server resolves raw_phrase when omitted.'),
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
