<?php

namespace App\Mcp\Tools;

use App\Mcp\Tools\Concerns\ResolvesWorkoutUser;
use App\Services\WorkoutMemory\CurrentUserResolver;
use App\Services\WorkoutMemory\ExerciseCreator;
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

#[Name('create_exercise')]
#[Description('Create a custom exercise only after discovery. Requires resolution_id evidence unless creation_reason is user_requested. Refuses likely duplicates and returns the existing exercise or bucket to use.')]
#[IsReadOnly(false)]
#[IsDestructive(false)]
#[IsOpenWorld(false)]
#[IsIdempotent]
class CreateExerciseTool extends Tool
{
    use ResolvesWorkoutUser;

    public function handle(Request $request, CurrentUserResolver $users, ExerciseCreator $creator): ResponseFactory
    {
        $validated = $request->validate([
            'name' => ['required', 'string', 'max:160'],
            'source_phrase' => ['sometimes', 'nullable', 'string', 'max:255'],
            'resolution_id' => ['sometimes', 'nullable', 'string'],
            'creation_reason' => ['required', 'in:no_match,separate_progression,user_requested,promote_bucket_variant'],
            'user_confirmed_duplicate_review' => ['sometimes', 'boolean'],
            'reviewed_candidate_ids' => ['sometimes', 'array'],
            'aliases' => ['sometimes', 'array'],
            'aliases.*' => ['string', 'max:160'],
            'category' => ['required', 'string', 'max:80'],
            'tracking_mode' => ['required', 'string', 'max:80'],
            'equipment' => ['sometimes', 'array'],
            'equipment.*' => ['string', 'max:80'],
            'primary_muscles' => ['sometimes', 'array'],
            'primary_muscles.*' => ['string', 'max:80'],
            'secondary_muscles' => ['sometimes', 'array'],
            'secondary_muscles.*' => ['string', 'max:80'],
            'tags' => ['sometimes', 'array'],
            'tags.*' => ['string', 'max:80'],
            'granularity' => ['sometimes', 'in:canonical,variant,bucket'],
            'external_load_allowed' => ['sometimes', 'boolean'],
            'parent_exercise_id' => ['sometimes', 'nullable', 'integer'],
            'variation_notes' => ['sometimes', 'nullable', 'string'],
            'phrase_to_remember' => ['sometimes', 'nullable', 'string', 'max:255'],
        ]);

        return $this->structured($creator->create($this->currentUser($users), $validated), 'Exercise creation handled.');
    }

    public function schema(JsonSchema $schema): array
    {
        return [
            'name' => $schema->string()->required(),
            'source_phrase' => $schema->string()->nullable(),
            'resolution_id' => $schema->string()->nullable()->description('Recent id from resolve_exercise_mentions or search_exercises.'),
            'creation_reason' => $schema->string()->enum(['no_match', 'separate_progression', 'user_requested', 'promote_bucket_variant'])->required(),
            'user_confirmed_duplicate_review' => $schema->boolean()->default(false),
            'reviewed_candidate_ids' => $schema->array()->items($schema->integer()),
            'aliases' => $schema->array()->items($schema->string()),
            'category' => $schema->string()->required(),
            'tracking_mode' => $schema->string()->required(),
            'equipment' => $schema->array()->items($schema->string()),
            'primary_muscles' => $schema->array()->items($schema->string()),
            'secondary_muscles' => $schema->array()->items($schema->string()),
            'tags' => $schema->array()->items($schema->string()),
            'granularity' => $schema->string()->enum(['canonical', 'variant', 'bucket'])->default('canonical'),
            'external_load_allowed' => $schema->boolean(),
            'parent_exercise_id' => $schema->integer()->nullable(),
            'variation_notes' => $schema->string()->nullable(),
            'phrase_to_remember' => $schema->string()->nullable(),
        ];
    }

    public function outputSchema(JsonSchema $schema): array
    {
        return $this->baseOutputSchema($schema, [
            'created_exercise' => $this->detailedExerciseSchema($schema)->nullable(),
            'similar_existing_exercises' => $schema->array()->items($this->resolverCandidateSchema($schema)),
            'warning' => $schema->string()->nullable(),
            'recommended_existing_exercise' => $this->exerciseSummarySchema($schema)->nullable(),
            'recommended_bucket_exercise' => $this->exerciseSummarySchema($schema)->nullable(),
        ]);
    }
}
