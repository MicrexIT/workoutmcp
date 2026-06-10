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
#[Description('Save a completed workout in one call. Exercise ids must already exist; this tool never creates exercises implicitly. Use resolve_exercise_mentions first and pass resolution_id unless the raw phrase directly matches the exercise name or alias.')]
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
            'exercises.*.exercise_id' => ['required', 'integer'],
            'exercises.*.resolution_id' => ['sometimes', 'nullable', 'string'],
            'exercises.*.raw_phrase' => ['sometimes', 'nullable', 'string'],
            'exercises.*.resolution_type' => ['required', 'string'],
            'exercises.*.variant_label' => ['sometimes', 'nullable', 'string'],
            'exercises.*.variant_description' => ['sometimes', 'nullable', 'string'],
            'exercises.*.notes' => ['sometimes', 'nullable', 'string'],
            'exercises.*.sets' => ['required', 'array', 'min:1'],
            'exercises.*.sets.*.reps' => ['sometimes', 'nullable', 'integer', 'min:0'],
            'exercises.*.sets.*.load_value' => ['sometimes', 'nullable', 'numeric'],
            'exercises.*.sets.*.load_unit' => ['sometimes', 'nullable', 'in:kg,lb'],
            'exercises.*.sets.*.load_type' => ['sometimes', 'nullable', 'in:implement,external,assistance,bodyweight,unknown'],
            'exercises.*.sets.*.duration_seconds' => ['sometimes', 'nullable', 'integer', 'min:0'],
            'exercises.*.sets.*.distance_value' => ['sometimes', 'nullable', 'numeric'],
            'exercises.*.sets.*.distance_unit' => ['sometimes', 'nullable', 'in:m,km,mi'],
            'exercises.*.sets.*.side' => ['sometimes', 'nullable', 'in:left,right,both,alternating'],
            'exercises.*.sets.*.success' => ['sometimes', 'nullable', 'boolean'],
            'exercises.*.sets.*.quality_rating' => ['sometimes', 'nullable', 'integer', 'min:1', 'max:5'],
            'exercises.*.sets.*.custom_metrics' => ['sometimes', 'nullable', 'array'],
            'exercises.*.sets.*.raw_set_text' => ['sometimes', 'nullable', 'string'],
            'exercises.*.sets.*.notes' => ['sometimes', 'nullable', 'string'],
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
            'exercises' => $schema->array()->items($schema->object([
                'exercise_id' => $schema->integer()->required(),
                'resolution_id' => $schema->string()->nullable(),
                'raw_phrase' => $schema->string()->nullable(),
                'resolution_type' => $schema->string()->required(),
                'variant_label' => $schema->string()->nullable(),
                'variant_description' => $schema->string()->nullable(),
                'notes' => $schema->string()->nullable(),
                'sets' => $schema->array()->items($schema->object([
                    'reps' => $schema->integer()->nullable(),
                    'load_value' => $schema->number()->nullable(),
                    'load_unit' => $schema->string()->enum(['kg', 'lb'])->nullable(),
                    'duration_seconds' => $schema->integer()->nullable(),
                    'distance_value' => $schema->number()->nullable(),
                    'distance_unit' => $schema->string()->enum(['m', 'km', 'mi'])->nullable(),
                ]))->required(),
            ]))->required(),
        ];
    }
}
