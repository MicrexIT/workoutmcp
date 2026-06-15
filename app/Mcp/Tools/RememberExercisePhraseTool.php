<?php

namespace App\Mcp\Tools;

use App\Mcp\Tools\Concerns\ResolvesWorkoutUser;
use App\Models\Exercise;
use App\Services\WorkoutMemory\CurrentUserResolver;
use App\Services\WorkoutMemory\ExerciseSerializer;
use App\Services\WorkoutMemory\WorkoutExerciseWriter;
use Illuminate\Contracts\JsonSchema\JsonSchema;
use Illuminate\Database\Eloquent\Builder;
use Laravel\Mcp\Request;
use Laravel\Mcp\ResponseFactory;
use Laravel\Mcp\Server\Attributes\Description;
use Laravel\Mcp\Server\Attributes\Name;
use Laravel\Mcp\Server\Tool;
use Laravel\Mcp\Server\Tools\Annotations\IsDestructive;
use Laravel\Mcp\Server\Tools\Annotations\IsIdempotent;
use Laravel\Mcp\Server\Tools\Annotations\IsOpenWorld;
use Laravel\Mcp\Server\Tools\Annotations\IsReadOnly;

#[Name('remember_exercise_phrase')]
#[Description('Teach the server that a phrase means a specific exercise for this user. Use it when the user corrects a match, confirms an assumed or auto-created mapping, or asks to remember wording like "when I say calves I mean seated calf raise". Future resolve and log calls map the phrase directly. This stores user-scoped phrase memory; it never edits the shared exercise catalog.')]
#[IsReadOnly(false)]
#[IsDestructive(false)]
#[IsOpenWorld(false)]
#[IsIdempotent]
class RememberExercisePhraseTool extends Tool
{
    use ResolvesWorkoutUser;

    public function handle(Request $request, CurrentUserResolver $users, WorkoutExerciseWriter $writer, ExerciseSerializer $serializer): ResponseFactory
    {
        $validated = $request->validate([
            'phrase' => ['required', 'string', 'max:255'],
            'exercise_id' => ['required', 'integer'],
            'variant_label' => ['sometimes', 'nullable', 'string', 'max:255'],
            'variant_description' => ['sometimes', 'nullable', 'string'],
        ]);

        $user = $this->currentUser($users);
        $exercise = Exercise::query()
            ->where(function (Builder $builder) use ($user): void {
                $builder->whereNull('user_id')->orWhere('user_id', $user->id);
            })
            ->find($validated['exercise_id']);

        if ($exercise === null) {
            return $this->structured([
                'refused' => true,
                'refusal_reason' => 'Exercise not found.',
                'phrase_memory' => null,
                'exercise' => null,
            ], 'Exercise phrase memory handled.');
        }

        $memory = $writer->upsertPhraseMemory(
            $user,
            $exercise,
            $validated['phrase'],
            $validated['variant_label'] ?? null,
            $validated['variant_description'] ?? null,
            confidence: 0.99,
        );

        return $this->structured([
            'refused' => false,
            'phrase_memory' => [
                'phrase' => $memory->phrase,
                'normalized_phrase' => $memory->normalized_phrase,
                'exercise_id' => $memory->exercise_id,
                'variant_label' => $memory->variant_label,
                'variant_description' => $memory->variant_description,
                'confidence' => (float) $memory->confidence,
                'usage_count' => (int) $memory->usage_count,
            ],
            'exercise' => $serializer->summary($exercise->loadMissing(['aliases', 'parent'])),
        ], 'Exercise phrase memory handled.');
    }

    public function schema(JsonSchema $schema): array
    {
        return [
            'phrase' => $schema->string()->required()->description('The user\'s wording, e.g. "calves" or "weighted pistols".'),
            'exercise_id' => $schema->integer()->required()->description('The exercise this phrase should resolve to.'),
            'variant_label' => $schema->string()->nullable()->description('Optional default variant label stored with the mapping (useful for bucket exercises).'),
            'variant_description' => $schema->string()->nullable(),
        ];
    }
}
