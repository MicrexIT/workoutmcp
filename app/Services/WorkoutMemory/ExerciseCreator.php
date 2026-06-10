<?php

namespace App\Services\WorkoutMemory;

use App\Models\Exercise;
use App\Models\ExercisePhraseMemory;
use App\Models\ExerciseResolutionAttempt;
use App\Models\User;
use Illuminate\Database\Eloquent\Builder;

class ExerciseCreator
{
    public function __construct(
        private readonly ExerciseResolver $resolver,
        private readonly ExerciseSerializer $serializer,
    ) {}

    /**
     * @param  array<string, mixed>  $input
     * @return array<string, mixed>
     */
    public function create(User $user, array $input): array
    {
        $name = trim((string) ($input['name'] ?? ''));
        $sourcePhrase = trim((string) ($input['source_phrase'] ?? $name));
        $creationReason = (string) ($input['creation_reason'] ?? '');
        $resolutionId = $input['resolution_id'] ?? null;
        $userConfirmedDuplicateReview = (bool) ($input['user_confirmed_duplicate_review'] ?? false);

        if ($name === '') {
            return $this->refused('Exercise name is required.');
        }

        $attempt = $resolutionId
            ? ExerciseResolutionAttempt::query()
                ->where('user_id', $user->id)
                ->where('resolution_id', $resolutionId)
                ->where(fn (Builder $builder) => $builder->whereNull('expires_at')->orWhere('expires_at', '>=', now()))
                ->first()
            : null;

        if ($attempt === null && $creationReason !== 'user_requested') {
            return $this->refused('create_exercise requires recent evidence from resolve_exercise_mentions or search_exercises unless the user explicitly asked to create a named exercise.');
        }

        $normalizedName = ExerciseResolver::normalize($name);
        $exactExisting = $this->visibleExercises($user)
            ->where('normalized_name', $normalizedName)
            ->with(['aliases', 'parent'])
            ->first();

        if ($exactExisting !== null) {
            return $this->refused(
                'An exercise with this canonical name already exists.',
                recommendedExisting: $exactExisting,
            );
        }

        if ($attempt !== null && $attempt->best_exercise_id !== null && $attempt->best_confidence >= 0.80 && ! $userConfirmedDuplicateReview) {
            $recommended = Exercise::query()->with(['aliases', 'parent'])->find($attempt->best_exercise_id);

            if ($recommended !== null && $recommended->granularity === 'bucket') {
                return $this->refused(
                    'Discovery found a strong bucket match. Use it with a variant label unless the user confirms separate progression history.',
                    recommendedBucket: $recommended,
                    similar: $attempt->candidates ?? [],
                );
            }

            return $this->refused(
                'Discovery found a strong existing exercise match. Reuse it unless the user confirms a duplicate review.',
                recommendedExisting: $recommended,
                similar: $attempt->candidates ?? [],
            );
        }

        $freshSearch = $this->resolver->search($user, ['query' => $name, 'limit' => 5], persistEvidence: false);
        $strongCandidate = collect($freshSearch['candidates'])
            ->first(fn (array $candidate): bool => $candidate['confidence'] >= 0.86);

        if ($strongCandidate !== null && ! $userConfirmedDuplicateReview) {
            $recommended = Exercise::query()->with(['aliases', 'parent'])->find($strongCandidate['exercise']['id']);

            return $this->refused(
                'A similar exercise already exists. Confirm duplicate review before creating a separate exercise.',
                recommendedExisting: $recommended,
                similar: $freshSearch['candidates'],
            );
        }

        $parent = isset($input['parent_exercise_id'])
            ? $this->visibleExercises($user)->find($input['parent_exercise_id'])
            : null;

        $exercise = $this->createExercise(
            $user,
            [
                'parent_exercise_id' => $parent?->id,
                'name' => $name,
                'category' => $input['category'] ?? 'other',
                'granularity' => $input['granularity'] ?? 'canonical',
                'tags' => array_values($input['tags'] ?? []),
                'primary_muscles' => array_values($input['primary_muscles'] ?? []),
                'secondary_muscles' => array_values($input['secondary_muscles'] ?? []),
                'primary_body_area' => $input['primary_body_area'] ?? ($input['primary_muscles'][0] ?? null),
                'equipment' => array_values($input['equipment'] ?? []),
                'tracking_mode' => $input['tracking_mode'] ?? 'load_reps',
                'unilateral' => $input['unilateral'] ?? null,
                'bodyweight' => $input['bodyweight'] ?? null,
                'external_load_allowed' => (bool) ($input['external_load_allowed'] ?? false),
                'variation_notes' => $input['variation_notes'] ?? null,
                'default_variant_policy' => $input['default_variant_policy'] ?? 'log_variant',
                'instructions' => $input['instructions'] ?? null,
                'safety_notes' => $input['safety_notes'] ?? null,
                'metadata' => [
                    'creation_reason' => $creationReason,
                    'source_phrase' => $sourcePhrase,
                    'reviewed_candidate_ids' => $input['reviewed_candidate_ids'] ?? [],
                ],
            ],
            aliases: $input['aliases'] ?? [],
            phraseToRemember: (string) ($input['phrase_to_remember'] ?? $sourcePhrase) !== ''
                ? (string) ($input['phrase_to_remember'] ?? $sourcePhrase)
                : null,
            attempt: $attempt,
        );

        return [
            'refused' => false,
            'created_exercise' => $this->serializer->detailed($exercise->fresh(['aliases', 'parent'])),
            'similar_existing_exercises' => $freshSearch['candidates'],
            'warning' => null,
            'refusal_reason' => null,
            'recommended_existing_exercise' => null,
            'recommended_bucket_exercise' => null,
        ];
    }

    /**
     * Create an exercise without discovery gates. Callers own duplicate checks and visibility rules.
     *
     * @param  array<string, mixed>  $attributes
     * @param  list<string>  $aliases
     */
    public function createExercise(
        User $user,
        array $attributes,
        array $aliases = [],
        ?string $phraseToRemember = null,
        float $phraseConfidence = 0.95,
        ?ExerciseResolutionAttempt $attempt = null,
    ): Exercise {
        $name = trim((string) ($attributes['name'] ?? ''));

        $exercise = Exercise::query()->create([
            'source' => 'user',
            ...$attributes,
            'user_id' => $user->id,
            'name' => $name,
            'canonical_name' => $attributes['canonical_name'] ?? $name,
            'normalized_name' => ExerciseResolver::normalize($name),
        ]);

        foreach (array_unique([$name, ...$aliases]) as $alias) {
            $exercise->aliases()->updateOrCreate(
                ['normalized_alias' => ExerciseResolver::normalize((string) $alias)],
                ['alias' => $alias, 'source' => 'user'],
            );
        }

        if ($phraseToRemember !== null && trim($phraseToRemember) !== '') {
            ExercisePhraseMemory::query()->updateOrCreate(
                [
                    'user_id' => $user->id,
                    'normalized_phrase' => ExerciseResolver::normalize($phraseToRemember),
                ],
                [
                    'exercise_id' => $exercise->id,
                    'phrase' => $phraseToRemember,
                    'confidence' => $phraseConfidence,
                    'usage_count' => 1,
                    'last_used_at' => now(),
                ],
            );
        }

        if ($attempt !== null) {
            $attempt->update(['created_exercise_id' => $exercise->id]);
        }

        return $exercise;
    }

    /**
     * @param  list<array<string, mixed>>  $similar
     * @return array<string, mixed>
     */
    private function refused(
        string $reason,
        ?Exercise $recommendedExisting = null,
        ?Exercise $recommendedBucket = null,
        array $similar = [],
    ): array {
        return [
            'refused' => true,
            'created_exercise' => null,
            'similar_existing_exercises' => $similar,
            'warning' => $reason,
            'refusal_reason' => $reason,
            'recommended_existing_exercise' => $recommendedExisting ? $this->serializer->summary($recommendedExisting) : null,
            'recommended_bucket_exercise' => $recommendedBucket ? $this->serializer->summary($recommendedBucket) : null,
        ];
    }

    private function visibleExercises(User $user): Builder
    {
        return Exercise::query()
            ->where(function (Builder $builder) use ($user): void {
                $builder->whereNull('user_id')->orWhere('user_id', $user->id);
            });
    }
}
