<?php

namespace App\Mcp\Tools;

use App\Mcp\Tools\Concerns\ResolvesWorkoutUser;
use App\Services\WorkoutMemory\CurrentUserResolver;
use App\Services\WorkoutMemory\ExerciseResolver;
use Illuminate\Contracts\JsonSchema\JsonSchema;
use Laravel\Mcp\Request;
use Laravel\Mcp\ResponseFactory;
use Laravel\Mcp\Server\Attributes\Description;
use Laravel\Mcp\Server\Attributes\Name;
use Laravel\Mcp\Server\Tool;
use Laravel\Mcp\Server\Tools\Annotations\IsDestructive;
use Laravel\Mcp\Server\Tools\Annotations\IsOpenWorld;
use Laravel\Mcp\Server\Tools\Annotations\IsReadOnly;

#[Name('resolve_exercise_mentions')]
#[Description('Resolve raw exercise phrases before logging. This tries to write short-lived discovery evidence but still returns usable matches if evidence persistence is unavailable. It does not create exercises or workouts. Each result includes log_entry_template, which can be copied directly into a log_workout or append_workout_exercise entry, and requires_variant_details for bucket exercises.')]
#[IsReadOnly(false)]
#[IsDestructive(false)]
#[IsOpenWorld(false)]
class ResolveExerciseMentionsTool extends Tool
{
    use ResolvesWorkoutUser;

    public function handle(Request $request, CurrentUserResolver $users, ExerciseResolver $resolver): ResponseFactory
    {
        $validated = $request->validate([
            'mentions' => ['required', 'array', 'min:1'],
            'mentions.*.raw_phrase' => ['required', 'string', 'max:255'],
            'mentions.*.context' => ['sometimes', 'nullable', 'string'],
            'workout_kind' => ['sometimes', 'nullable', 'string'],
            'allow_bucket_suggestions' => ['sometimes', 'boolean'],
        ]);

        return $this->structured([
            'results' => $resolver->resolveMentions(
                $this->currentUser($users),
                $validated['mentions'],
                $validated['workout_kind'] ?? null,
                $validated['allow_bucket_suggestions'] ?? true,
            ),
        ], 'Exercise mentions resolved.');
    }

    public function schema(JsonSchema $schema): array
    {
        return [
            'mentions' => $schema->array()->items($schema->object([
                'raw_phrase' => $schema->string()->required()->description('Raw exercise name or description from the user.'),
                'context' => $schema->string()->nullable()->description('Optional surrounding text that helps resolution.'),
            ]))->required(),
            'workout_kind' => $schema->string()->nullable(),
            'allow_bucket_suggestions' => $schema->boolean()->default(true),
        ];
    }

    public function outputSchema(JsonSchema $schema): array
    {
        return $this->baseOutputSchema($schema, [
            'results' => $schema->array()->required()->items($this->exerciseResolutionResultSchema($schema)),
        ]);
    }
}
