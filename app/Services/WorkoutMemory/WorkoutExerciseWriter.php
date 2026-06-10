<?php

namespace App\Services\WorkoutMemory;

use App\Models\Exercise;
use App\Models\ExercisePhraseMemory;
use App\Models\ExerciseResolutionAttempt;
use App\Models\User;
use App\Models\WorkoutExercise;
use App\Models\WorkoutSession;
use Illuminate\Database\Eloquent\Builder;

class WorkoutExerciseWriter
{
    public function __construct(private readonly UnitNormalizer $unitNormalizer) {}

    /**
     * @param  list<array<string, mixed>>  $exercises
     * @return list<array<string, mixed>>
     */
    public function validateExercises(User $user, array $exercises): array
    {
        if ($exercises === []) {
            return [['message' => 'At least one exercise is required.']];
        }

        $invalid = [];

        foreach ($exercises as $exerciseInput) {
            $invalid = [
                ...$invalid,
                ...$this->validateExercise($user, $exerciseInput),
            ];
        }

        return $invalid;
    }

    /**
     * @param  array<string, mixed>  $exerciseInput
     * @return list<array<string, mixed>>
     */
    public function validateExercise(User $user, array $exerciseInput): array
    {
        $exercise = $this->visibleExercises($user)->find($exerciseInput['exercise_id'] ?? null);

        if ($exercise === null) {
            return [[
                'raw_phrase' => $exerciseInput['raw_phrase'] ?? null,
                'message' => 'Exercise id is missing or unknown.',
            ]];
        }

        $invalid = $this->validateResolutionEvidence($user, $exercise, $exerciseInput);

        if ($exercise->granularity === 'bucket' && empty($exerciseInput['variant_label']) && empty($exerciseInput['variant_description']) && empty($exerciseInput['notes'])) {
            $invalid[] = [
                'exercise_id' => $exercise->id,
                'raw_phrase' => $exerciseInput['raw_phrase'] ?? null,
                'message' => 'Bucket entries need a variant label, variant description, or notes.',
            ];
        }

        return $invalid;
    }

    /**
     * @param  array<string, mixed>  $exerciseInput
     */
    public function createWorkoutExercise(User $user, WorkoutSession $session, array $exerciseInput, ?int $sortOrder = null): WorkoutExercise
    {
        $exercise = $this->visibleExercises($user)->findOrFail($exerciseInput['exercise_id']);
        $attempt = $this->resolutionAttempt($user, $exerciseInput['resolution_id'] ?? null);

        $workoutExercise = $session->exercises()->create([
            'exercise_id' => $exercise->id,
            'sort_order' => $sortOrder ?? ((int) ($session->exercises()->max('sort_order') ?? 0) + 1),
            'name_snapshot' => $exercise->name,
            'tracking_mode_snapshot' => $exercise->tracking_mode,
            'exercise_resolution_attempt_id' => $attempt?->id,
            'raw_phrase' => $exerciseInput['raw_phrase'] ?? null,
            'resolution_type' => $exerciseInput['resolution_type'] ?? 'exact',
            'variant_label' => $exerciseInput['variant_label'] ?? null,
            'variant_description' => $exerciseInput['variant_description'] ?? null,
            'prescription' => $exerciseInput['prescription'] ?? null,
            'notes' => $exerciseInput['notes'] ?? null,
        ]);

        foreach (array_values($exerciseInput['sets'] ?? []) as $setIndex => $setInput) {
            $this->createSet($workoutExercise, $setInput, $setInput['set_number'] ?? ($setIndex + 1));
        }

        $this->rememberPhrase($user, $exerciseInput, $exercise, $attempt);

        return $workoutExercise;
    }

    /**
     * @param  array<string, mixed>  $setInput
     */
    public function createSet(WorkoutExercise $workoutExercise, array $setInput, ?int $setNumber = null): void
    {
        $workoutExercise->sets()->create([
            'set_number' => $setNumber ?? ((int) ($workoutExercise->sets()->max('set_number') ?? 0) + 1),
            ...$this->setPayload($setInput),
        ]);
    }

    /**
     * @param  array<string, mixed>  $setInput
     * @return array<string, mixed>
     */
    public function setPayload(array $setInput): array
    {
        return [
            'reps' => $setInput['reps'] ?? null,
            'load_kg' => $this->unitNormalizer->normalizeLoad(
                isset($setInput['load_value']) ? (float) $setInput['load_value'] : null,
                $setInput['load_unit'] ?? null,
            ),
            'load_type' => $setInput['load_type'] ?? null,
            'duration_seconds' => $setInput['duration_seconds'] ?? null,
            'distance_meters' => $this->unitNormalizer->normalizeDistance(
                isset($setInput['distance_value']) ? (float) $setInput['distance_value'] : null,
                $setInput['distance_unit'] ?? null,
            ),
            'rpe' => $setInput['rpe'] ?? null,
            'rir' => $setInput['rir'] ?? null,
            'side' => $setInput['side'] ?? null,
            'success' => $setInput['success'] ?? null,
            'quality_rating' => $setInput['quality_rating'] ?? null,
            'is_warmup' => (bool) ($setInput['is_warmup'] ?? false),
            'raw_set_text' => $setInput['raw_set_text'] ?? null,
            'custom_metrics' => $setInput['custom_metrics'] ?? null,
            'notes' => $setInput['notes'] ?? null,
        ];
    }

    /**
     * @param  array<string, mixed>  $exerciseInput
     * @return list<array<string, mixed>>
     */
    private function validateResolutionEvidence(User $user, Exercise $exercise, array $exerciseInput): array
    {
        $rawPhrase = trim((string) ($exerciseInput['raw_phrase'] ?? ''));
        $resolutionId = $exerciseInput['resolution_id'] ?? null;
        $resolutionType = (string) ($exerciseInput['resolution_type'] ?? 'exact');
        $attempt = $this->resolutionAttempt($user, $resolutionId);
        $invalid = [];

        if (in_array($resolutionType, ['ambiguous', 'create_suggestion', 'manual_assumption'], true)) {
            $invalid[] = [
                'exercise_id' => $exercise->id,
                'raw_phrase' => $exerciseInput['raw_phrase'] ?? null,
                'message' => 'Resolution is not safe enough to log.',
            ];
        }

        if ($this->hasResolutionId($resolutionId) && $attempt === null) {
            $invalid[] = [
                'exercise_id' => $exercise->id,
                'raw_phrase' => $exerciseInput['raw_phrase'] ?? null,
                'message' => 'Resolution id is missing, expired, or belongs to another user.',
            ];
        }

        if ($attempt !== null) {
            return [
                ...$invalid,
                ...$this->validateAttemptMatchesExercise($exercise, $exerciseInput, $attempt, $rawPhrase),
            ];
        }

        if ($rawPhrase !== '' && ! $this->directlyMatchesExercise($exercise, $rawPhrase)) {
            $invalid[] = [
                'exercise_id' => $exercise->id,
                'raw_phrase' => $exerciseInput['raw_phrase'] ?? null,
                'message' => 'Raw phrase must be resolved or directly match the exercise before logging.',
            ];
        }

        return $invalid;
    }

    /**
     * @param  array<string, mixed>  $exerciseInput
     * @return list<array<string, mixed>>
     */
    private function validateAttemptMatchesExercise(Exercise $exercise, array $exerciseInput, ExerciseResolutionAttempt $attempt, string $rawPhrase): array
    {
        $invalid = [];
        $resolutionType = (string) ($exerciseInput['resolution_type'] ?? 'exact');
        $normalizedRawPhrase = ExerciseResolver::normalize($rawPhrase);

        if ($normalizedRawPhrase !== '' && $normalizedRawPhrase !== $attempt->normalized_phrase) {
            $invalid[] = [
                'exercise_id' => $exercise->id,
                'raw_phrase' => $exerciseInput['raw_phrase'] ?? null,
                'message' => 'Resolution id was created for a different raw phrase.',
            ];
        }

        if (! in_array($attempt->suggested_action, ['use_existing', 'use_bucket'], true)) {
            $invalid[] = [
                'exercise_id' => $exercise->id,
                'raw_phrase' => $exerciseInput['raw_phrase'] ?? null,
                'message' => 'Resolver evidence is still ambiguous and must not be logged automatically.',
            ];
        }

        if ((int) $attempt->best_exercise_id !== (int) $exercise->id) {
            $invalid[] = [
                'exercise_id' => $exercise->id,
                'raw_phrase' => $exerciseInput['raw_phrase'] ?? null,
                'message' => 'Exercise id does not match the resolver evidence.',
            ];
        }

        if (! $this->resolutionTypesMatch($resolutionType, (string) $attempt->best_resolution)) {
            $invalid[] = [
                'exercise_id' => $exercise->id,
                'raw_phrase' => $exerciseInput['raw_phrase'] ?? null,
                'message' => 'Resolution type does not match the resolver evidence.',
            ];
        }

        return $invalid;
    }

    private function resolutionTypesMatch(string $provided, string $resolved): bool
    {
        if (in_array($provided, ['bucket', 'bucket_suggestion'], true)) {
            return $resolved === 'bucket_suggestion';
        }

        return in_array($provided, ['exact', 'alias', 'variant', 'phrase_memory'], true)
            && in_array($resolved, ['exact', 'alias', 'variant', 'phrase_memory'], true);
    }

    private function directlyMatchesExercise(Exercise $exercise, string $rawPhrase): bool
    {
        $normalizedRawPhrase = ExerciseResolver::normalize($rawPhrase);

        if ($normalizedRawPhrase === '' || $normalizedRawPhrase === $exercise->normalized_name) {
            return true;
        }

        return $exercise->aliases()
            ->where('normalized_alias', $normalizedRawPhrase)
            ->exists();
    }

    private function hasResolutionId(mixed $resolutionId): bool
    {
        return is_string($resolutionId) && trim($resolutionId) !== '';
    }

    private function resolutionAttempt(User $user, mixed $resolutionId): ?ExerciseResolutionAttempt
    {
        if (! is_string($resolutionId) || $resolutionId === '') {
            return null;
        }

        return ExerciseResolutionAttempt::query()
            ->where('user_id', $user->id)
            ->where('resolution_id', $resolutionId)
            ->where('expires_at', '>', now())
            ->first();
    }

    /**
     * @param  array<string, mixed>  $exerciseInput
     */
    private function rememberPhrase(User $user, array $exerciseInput, Exercise $exercise, ?ExerciseResolutionAttempt $attempt): void
    {
        $rawPhrase = trim((string) ($exerciseInput['raw_phrase'] ?? ''));

        if ($rawPhrase === '') {
            return;
        }

        $confidence = $attempt?->best_confidence ?? (in_array($exerciseInput['resolution_type'] ?? '', ['exact', 'alias', 'phrase_memory'], true) ? 0.95 : 0.80);

        if ($confidence < 0.80) {
            return;
        }

        $memory = ExercisePhraseMemory::query()->firstOrNew([
            'user_id' => $user->id,
            'normalized_phrase' => ExerciseResolver::normalize($rawPhrase),
        ]);

        $memory->fill([
            'exercise_id' => $exercise->id,
            'phrase' => $rawPhrase,
            'variant_label' => $exerciseInput['variant_label'] ?? null,
            'variant_description' => $exerciseInput['variant_description'] ?? null,
            'confidence' => $confidence,
            'usage_count' => ((int) $memory->usage_count) + 1,
            'last_used_at' => now(),
        ])->save();
    }

    private function visibleExercises(User $user): Builder
    {
        return Exercise::query()
            ->where(function (Builder $builder) use ($user): void {
                $builder->whereNull('user_id')->orWhere('user_id', $user->id);
            });
    }
}
