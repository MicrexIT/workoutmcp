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

#[Name('search_exercises')]
#[Description('Search exercises by name, alias, muscle, equipment, category, tracking mode, tags, or granularity. Tries to persist discovery evidence when query is present, but still returns matches if evidence persistence is unavailable.')]
#[IsReadOnly(false)]
#[IsDestructive(false)]
#[IsOpenWorld(false)]
class SearchExercisesTool extends Tool
{
    use ResolvesWorkoutUser;

    public function handle(Request $request, CurrentUserResolver $users, ExerciseResolver $resolver): ResponseFactory
    {
        $validated = $request->validate([
            'query' => ['sometimes', 'nullable', 'string', 'max:255'],
            'equipment' => ['sometimes', 'array'],
            'equipment.*' => ['string'],
            'category' => ['sometimes', 'nullable', 'string'],
            'muscle' => ['sometimes', 'nullable', 'string'],
            'tags' => ['sometimes', 'array'],
            'tags.*' => ['string'],
            'granularity' => ['sometimes', 'nullable', 'in:canonical,variant,bucket'],
            'tracking_mode' => ['sometimes', 'nullable', 'string'],
            'limit' => ['sometimes', 'integer', 'min:1', 'max:50'],
        ]);

        return $this->structured($resolver->search($this->currentUser($users), $validated), 'Exercise search complete.');
    }

    public function schema(JsonSchema $schema): array
    {
        return [
            'query' => $schema->string()->nullable(),
            'equipment' => $schema->array()->items($schema->string()),
            'category' => $schema->string()->nullable(),
            'muscle' => $schema->string()->nullable(),
            'tags' => $schema->array()->items($schema->string()),
            'granularity' => $schema->string()->enum(['canonical', 'variant', 'bucket'])->nullable(),
            'tracking_mode' => $schema->string()->nullable(),
            'limit' => $schema->integer()->default(10),
        ];
    }

    public function outputSchema(JsonSchema $schema): array
    {
        return $this->baseOutputSchema($schema, [
            'resolution_id' => $schema->string()->required()->nullable(),
            'query' => $schema->string()->required(),
            'matches' => $schema->array()->required()->items($this->exerciseSummarySchema($schema)),
            'candidates' => $schema->array()->required()->items($this->resolverCandidateSchema($schema)),
            'creating_new_exercise_recommended' => $schema->boolean()->required(),
            'duplicate_risk' => $schema->string()->required(),
            'evidence_persisted' => $schema->boolean()->required(),
            'evidence_persistence_warning' => $schema->string()->required()->nullable(),
        ]);
    }
}
