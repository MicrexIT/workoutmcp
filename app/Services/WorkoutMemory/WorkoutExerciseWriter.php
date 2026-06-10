<?php

namespace App\Services\WorkoutMemory;

use App\Models\Exercise;
use App\Models\ExercisePhraseMemory;
use App\Models\ExerciseResolutionAttempt;
use App\Models\User;
use App\Models\WorkoutExercise;
use App\Models\WorkoutSession;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Str;

class WorkoutExerciseWriter
{
    public function __construct(
        private readonly UnitNormalizer $unitNormalizer,
        private readonly ExerciseResolver $resolver,
        private readonly ExerciseCreator $creator,
        private readonly ExerciseSerializer $serializer,
    ) {}

    /**
     * Structural validation only. Resolution never refuses an entry: it escalates from
     * existing matches to bucket fallbacks to flagged auto-creation inside createWorkoutExercise.
     *
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
        if (ExerciseResolver::normalize((string) ($exerciseInput['raw_phrase'] ?? '')) !== '') {
            return [];
        }

        if ($this->visibleExercises($user)->find($exerciseInput['exercise_id'] ?? null) !== null) {
            return [];
        }

        return [[
            'raw_phrase' => $exerciseInput['raw_phrase'] ?? null,
            'message' => 'Entry needs a raw_phrase or a known exercise_id.',
        ]];
    }

    /**
     * @param  array<string, mixed>  $exerciseInput
     * @return array{workout_exercise: WorkoutExercise, outcome: array<string, mixed>}
     */
    public function createWorkoutExercise(User $user, WorkoutSession $session, array $exerciseInput, ?int $sortOrder = null): array
    {
        $resolved = $this->resolveEntry($user, $exerciseInput);

        /** @var Exercise $exercise */
        $exercise = $resolved['exercise'];
        $rawPhrase = $resolved['raw_phrase'];

        $variantLabel = $exerciseInput['variant_label'] ?? $resolved['variant_label'];
        $variantDescription = $exerciseInput['variant_description'] ?? $resolved['variant_description'];
        $variantLabelAutofilled = false;

        if ($exercise->granularity === 'bucket' && empty($variantLabel) && empty($variantDescription) && empty($exerciseInput['notes']) && ($rawPhrase ?? '') !== '') {
            $variantLabel = Str::limit((string) $rawPhrase, 255, '');
            $variantLabelAutofilled = true;
        }

        $workoutExercise = $session->exercises()->create([
            'exercise_id' => $exercise->id,
            'sort_order' => $sortOrder ?? ((int) ($session->exercises()->max('sort_order') ?? 0) + 1),
            'name_snapshot' => $exercise->name,
            'tracking_mode_snapshot' => $exercise->tracking_mode,
            'exercise_resolution_attempt_id' => $resolved['attempt']?->id,
            'raw_phrase' => $rawPhrase,
            'resolution_type' => $resolved['resolution_type'],
            'variant_label' => $variantLabel,
            'variant_description' => $variantDescription,
            'prescription' => $exerciseInput['prescription'] ?? null,
            'notes' => $exerciseInput['notes'] ?? null,
        ]);

        foreach (array_values($exerciseInput['sets'] ?? []) as $setIndex => $setInput) {
            $this->createSet($workoutExercise, $setInput, $setInput['set_number'] ?? ($setIndex + 1));
        }

        $this->rememberPhrase($user, $resolved, $exercise, $variantLabel, $variantDescription);

        return [
            'workout_exercise' => $workoutExercise,
            'outcome' => $this->outcome($resolved, $exercise, $variantLabelAutofilled),
        ];
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

    public function upsertPhraseMemory(
        User $user,
        Exercise $exercise,
        string $phrase,
        ?string $variantLabel = null,
        ?string $variantDescription = null,
        float $confidence = 0.95,
    ): ExercisePhraseMemory {
        $memory = ExercisePhraseMemory::query()->firstOrNew([
            'user_id' => $user->id,
            'normalized_phrase' => ExerciseResolver::normalize($phrase),
        ]);

        $memory->fill([
            'exercise_id' => $exercise->id,
            'phrase' => $phrase,
            'variant_label' => $variantLabel,
            'variant_description' => $variantDescription,
            'confidence' => $confidence,
            'usage_count' => ((int) $memory->usage_count) + 1,
            'last_used_at' => now(),
        ])->save();

        return $memory;
    }

    /**
     * Collapse per-entry outcomes into the shared response keys used by all logging tools.
     *
     * @param  list<array<string, mixed>>  $outcomes
     * @return array<string, mixed>
     */
    public function outcomeSummary(array $outcomes): array
    {
        return [
            'resolution_outcomes' => $outcomes,
            'auto_created_exercises' => collect($outcomes)
                ->filter(fn (array $outcome): bool => (bool) $outcome['auto_created'])
                ->map(fn (array $outcome): array => [
                    'exercise' => $outcome['exercise_summary'] ?? null,
                    'created_from_phrase' => $outcome['created_from_phrase'] ?? $outcome['raw_phrase'],
                    'needs_review' => true,
                ])
                ->values()
                ->all(),
            'assumed_matches' => collect($outcomes)
                ->filter(fn (array $outcome): bool => $outcome['method'] === 'assumed')
                ->map(fn (array $outcome): array => [
                    'raw_phrase' => $outcome['raw_phrase'],
                    'exercise_id' => $outcome['exercise_id'],
                    'exercise_name' => $outcome['exercise_name'],
                    'confidence' => $outcome['confidence'],
                    'alternatives' => $outcome['alternatives'],
                ])
                ->values()
                ->all(),
        ];
    }

    /**
     * Server-authoritative resolution ladder: resolver evidence, then the explicitly
     * provided exercise, then phrase resolution escalating to flagged auto-creation.
     *
     * @param  array<string, mixed>  $exerciseInput
     * @return array<string, mixed>
     */
    private function resolveEntry(User $user, array $exerciseInput): array
    {
        $rawPhrase = trim((string) ($exerciseInput['raw_phrase'] ?? ''));
        $attempt = $this->resolutionAttempt($user, $exerciseInput['resolution_id'] ?? null);
        $providedExercise = $this->visibleExercises($user)->find($exerciseInput['exercise_id'] ?? null);

        if ($attempt !== null) {
            $resolved = $this->resolveFromAttempt($user, $attempt, $providedExercise, $rawPhrase);

            if ($resolved !== null) {
                return $resolved;
            }
        }

        if ($providedExercise !== null) {
            return $this->resolveProvidedExercise($user, $providedExercise, $rawPhrase, $attempt);
        }

        return $this->resolvePhrase($user, $rawPhrase, $exerciseInput);
    }

    /**
     * @return array<string, mixed>|null
     */
    private function resolveFromAttempt(User $user, ExerciseResolutionAttempt $attempt, ?Exercise $providedExercise, string $rawPhrase): ?array
    {
        $candidates = collect($attempt->candidates ?? []);

        if ($providedExercise !== null) {
            $match = $candidates->first(
                fn (array $candidate): bool => (int) ($candidate['exercise']['id'] ?? 0) === (int) $providedExercise->id,
            );

            if ($match === null) {
                return null;
            }

            return $this->attemptOutcome($attempt, $providedExercise, $match, $rawPhrase, 'evidence', []);
        }

        $best = $attempt->best_exercise_id !== null
            ? $this->visibleExercises($user)->find($attempt->best_exercise_id)
            : null;

        if ($best === null) {
            return null;
        }

        $match = $candidates->first(
            fn (array $candidate): bool => (int) ($candidate['exercise']['id'] ?? 0) === (int) $best->id,
        ) ?? ['resolution' => (string) $attempt->best_resolution, 'confidence' => (float) $attempt->best_confidence];

        if ((float) ($match['confidence'] ?? 0) >= 0.80) {
            return $this->attemptOutcome($attempt, $best, $match, $rawPhrase, 'evidence', []);
        }

        return $this->attemptOutcome(
            $attempt,
            $best,
            $match,
            $rawPhrase,
            'assumed',
            $this->alternativesFromCandidates($candidates->all(), (int) $best->id),
        );
    }

    /**
     * @param  array<string, mixed>  $match
     * @param  list<array<string, mixed>>  $alternatives
     * @return array<string, mixed>
     */
    private function attemptOutcome(ExerciseResolutionAttempt $attempt, Exercise $exercise, array $match, string $rawPhrase, string $method, array $alternatives): array
    {
        $resolutionType = (string) ($match['resolution'] ?? 'exact');

        if ($resolutionType === 'bucket_suggestion') {
            $resolutionType = 'bucket';
        }

        if ($method === 'assumed') {
            $resolutionType = 'manual_assumption';
        }

        return [
            'exercise' => $exercise,
            'attempt' => $attempt,
            'raw_phrase' => $rawPhrase !== '' ? $rawPhrase : $attempt->raw_phrase,
            'resolution_type' => $resolutionType,
            'method' => $method,
            'confidence' => (float) ($match['confidence'] ?? 0),
            'alternatives' => $alternatives,
            'variant_label' => null,
            'variant_description' => null,
            'auto_created' => false,
        ];
    }

    /**
     * An explicitly provided exercise id always wins; derive how it relates to the phrase.
     *
     * @return array<string, mixed>
     */
    private function resolveProvidedExercise(User $user, Exercise $exercise, string $rawPhrase, ?ExerciseResolutionAttempt $attempt): array
    {
        $base = [
            'exercise' => $exercise,
            'attempt' => $attempt,
            'raw_phrase' => $rawPhrase !== '' ? $rawPhrase : null,
            'alternatives' => [],
            'variant_label' => null,
            'variant_description' => null,
            'auto_created' => false,
        ];

        $normalized = ExerciseResolver::normalize($rawPhrase);

        if ($normalized === '' || $normalized === $exercise->normalized_name) {
            return [...$base, 'resolution_type' => 'exact', 'method' => 'exact', 'confidence' => 1.0];
        }

        if ($exercise->aliases()->where('normalized_alias', $normalized)->exists()) {
            return [...$base, 'resolution_type' => 'alias', 'method' => 'alias', 'confidence' => 0.98];
        }

        $memory = ExercisePhraseMemory::query()
            ->where('user_id', $user->id)
            ->where('normalized_phrase', $normalized)
            ->first();

        if ($memory !== null && (int) $memory->exercise_id === (int) $exercise->id) {
            return [
                ...$base,
                'resolution_type' => 'phrase_memory',
                'method' => 'phrase_memory',
                'confidence' => 0.97,
                'variant_label' => $memory->variant_label,
                'variant_description' => $memory->variant_description,
            ];
        }

        $resolution = $this->resolver->resolveForLogging($user, $rawPhrase);
        $base['attempt'] = $attempt ?? $this->resolutionAttempt($user, $resolution['resolution_id'] ?? null);

        $candidate = collect($resolution['candidates'] ?? [])->first(
            fn (array $item): bool => (int) ($item['exercise']['id'] ?? 0) === (int) $exercise->id,
        );

        if ($candidate !== null && (float) ($candidate['confidence'] ?? 0) >= 0.80) {
            $resolutionType = (string) $candidate['resolution'];

            return [
                ...$base,
                'resolution_type' => $resolutionType === 'bucket_suggestion' ? 'bucket' : $resolutionType,
                'method' => 'resolved',
                'confidence' => (float) $candidate['confidence'],
            ];
        }

        return [
            ...$base,
            'resolution_type' => 'manual_assumption',
            'method' => 'assumed',
            'confidence' => (float) ($candidate['confidence'] ?? 0.50),
            'alternatives' => $this->alternativesFromCandidates($resolution['candidates'] ?? [], (int) $exercise->id),
        ];
    }

    /**
     * @param  array<string, mixed>  $exerciseInput
     * @return array<string, mixed>
     */
    private function resolvePhrase(User $user, string $rawPhrase, array $exerciseInput): array
    {
        $resolution = $this->resolver->resolveForLogging($user, $rawPhrase);
        $resolutionType = (string) ($resolution['resolution'] ?? 'create_suggestion');

        $base = [
            'attempt' => $this->resolutionAttempt($user, $resolution['resolution_id'] ?? null),
            'raw_phrase' => $rawPhrase,
            'alternatives' => [],
            'variant_label' => null,
            'variant_description' => null,
            'auto_created' => false,
        ];

        $bestSummary = $resolution['exercise'] ?? null;

        if ($bestSummary !== null) {
            $exercise = $this->visibleExercises($user)->find($bestSummary['id'] ?? null);

            if ($exercise !== null) {
                return [
                    ...$base,
                    'exercise' => $exercise,
                    'resolution_type' => $resolutionType,
                    'method' => $resolutionType === 'phrase_memory' ? 'phrase_memory' : 'resolved',
                    'confidence' => (float) ($resolution['confidence'] ?? 0),
                    'variant_label' => $resolution['variant_label'] ?? null,
                    'variant_description' => $resolution['variant_description'] ?? null,
                ];
            }
        }

        $bucketSummary = $resolution['recommended_bucket_exercise'] ?? null;

        if (($resolution['suggested_action'] ?? null) === 'use_bucket' && $bucketSummary !== null) {
            $bucket = $this->visibleExercises($user)->find($bucketSummary['id'] ?? null);

            if ($bucket !== null) {
                return [
                    ...$base,
                    'exercise' => $bucket,
                    'resolution_type' => 'bucket',
                    'method' => 'bucket_fallback',
                    'confidence' => (float) ($resolution['confidence'] ?? 0.72),
                ];
            }
        }

        $topCandidate = collect($resolution['candidates'] ?? [])->first(
            fn (array $candidate): bool => ($candidate['resolution'] ?? '') !== 'bucket_suggestion'
                && (float) ($candidate['confidence'] ?? 0) >= 0.60,
        );

        if ($topCandidate !== null) {
            $exercise = $this->visibleExercises($user)->find($topCandidate['exercise']['id'] ?? null);

            if ($exercise !== null) {
                return [
                    ...$base,
                    'exercise' => $exercise,
                    'resolution_type' => 'manual_assumption',
                    'method' => 'assumed',
                    'confidence' => (float) $topCandidate['confidence'],
                    'alternatives' => $this->alternativesFromCandidates($resolution['candidates'] ?? [], (int) $exercise->id),
                ];
            }
        }

        return $this->autoCreate($user, $rawPhrase, $exerciseInput, $base);
    }

    /**
     * Last resort: create a clearly-flagged user-scoped exercise rather than dropping the entry.
     *
     * @param  array<string, mixed>  $exerciseInput
     * @param  array<string, mixed>  $base
     * @return array<string, mixed>
     */
    private function autoCreate(User $user, string $rawPhrase, array $exerciseInput, array $base): array
    {
        $existing = $this->visibleExercises($user)
            ->where('normalized_name', ExerciseResolver::normalize($rawPhrase))
            ->first();

        if ($existing !== null) {
            return [
                ...$base,
                'exercise' => $existing,
                'resolution_type' => 'exact',
                'method' => 'resolved',
                'confidence' => 1.0,
            ];
        }

        $sets = array_values($exerciseInput['sets'] ?? []);
        $hasLoad = collect($sets)->contains(fn (array $set): bool => isset($set['load_value']));

        $exercise = $this->creator->createExercise(
            $user,
            [
                'name' => (string) Str::of($rawPhrase)->squish()->title(),
                'source' => 'chatgpt_auto',
                'category' => $this->inferCategory($rawPhrase),
                'granularity' => 'canonical',
                'tracking_mode' => $this->inferTrackingMode($sets, $rawPhrase),
                'external_load_allowed' => $hasLoad,
                'metadata' => [
                    'auto_created' => true,
                    'created_from_phrase' => $rawPhrase,
                    'needs_review' => true,
                    'creation_reason' => 'auto_logged',
                ],
            ],
            aliases: [$rawPhrase],
            phraseToRemember: $rawPhrase,
            attempt: $base['attempt'],
        );

        return [
            ...$base,
            'exercise' => $exercise,
            'resolution_type' => 'auto_created',
            'method' => 'auto_created',
            'confidence' => 0.95,
            'auto_created' => true,
        ];
    }

    /**
     * @param  list<array<string, mixed>>  $sets
     */
    private function inferTrackingMode(array $sets, string $rawPhrase): string
    {
        $sets = collect($sets);
        $hasLoad = $sets->contains(fn (array $set): bool => isset($set['load_value']));
        $hasReps = $sets->contains(fn (array $set): bool => isset($set['reps']));
        $hasDuration = $sets->contains(fn (array $set): bool => isset($set['duration_seconds']));
        $hasDistance = $sets->contains(fn (array $set): bool => isset($set['distance_value']));

        return match (true) {
            $hasDistance && $hasDuration => 'time_distance',
            $hasDistance => 'distance',
            $hasLoad && $hasReps => 'load_reps',
            $hasDuration && ! $hasReps => Str::contains(ExerciseResolver::normalize($rawPhrase), ['hold', 'hang', 'plank']) ? 'hold' : 'time',
            $hasReps => 'reps',
            default => 'mixed',
        };
    }

    private function inferCategory(string $rawPhrase): string
    {
        $normalized = ExerciseResolver::normalize($rawPhrase);
        $tokens = collect(explode(' ', $normalized));

        return match (true) {
            Str::contains($normalized, ['stretch', 'mobility']) => 'mobility',
            Str::contains($normalized, 'handstand') => 'handstand',
            $tokens->intersect(['ring', 'rings'])->isNotEmpty() => 'rings',
            $tokens->intersect(['run', 'running', 'ride', 'riding', 'row', 'rowing', 'swim', 'swimming', 'bike', 'cycling', 'sprint', 'sprints'])->isNotEmpty() => 'conditioning',
            default => 'other',
        };
    }

    /**
     * @param  list<array<string, mixed>>  $candidates
     * @return list<array<string, mixed>>
     */
    private function alternativesFromCandidates(array $candidates, ?int $excludeExerciseId = null): array
    {
        return collect($candidates)
            ->filter(fn (array $candidate): bool => (float) ($candidate['confidence'] ?? 0) >= 0.60)
            ->reject(fn (array $candidate): bool => $excludeExerciseId !== null
                && (int) ($candidate['exercise']['id'] ?? 0) === $excludeExerciseId)
            ->take(3)
            ->map(fn (array $candidate): array => [
                'exercise_id' => (int) ($candidate['exercise']['id'] ?? 0),
                'exercise_name' => (string) ($candidate['exercise']['name'] ?? ''),
                'confidence' => (float) ($candidate['confidence'] ?? 0),
                'resolution' => (string) ($candidate['resolution'] ?? ''),
            ])
            ->values()
            ->all();
    }

    /**
     * @param  array<string, mixed>  $resolved
     * @return array<string, mixed>
     */
    private function outcome(array $resolved, Exercise $exercise, bool $variantLabelAutofilled): array
    {
        $outcome = [
            'raw_phrase' => $resolved['raw_phrase'],
            'exercise_id' => $exercise->id,
            'exercise_name' => $exercise->name,
            'method' => $resolved['method'],
            'resolution_type' => $resolved['resolution_type'],
            'confidence' => round((float) $resolved['confidence'], 2),
            'auto_created' => $resolved['auto_created'],
            'variant_label_autofilled' => $variantLabelAutofilled,
            'alternatives' => $resolved['alternatives'],
        ];

        if ($resolved['auto_created']) {
            $outcome['exercise_summary'] = $this->serializer->summary($exercise->fresh(['aliases', 'parent']));
            $outcome['created_from_phrase'] = $resolved['raw_phrase'];
        }

        return $outcome;
    }

    /**
     * Phrase memory must reflect confirmed mappings only: assumed matches and bucket
     * fallbacks are guesses, and auto-created exercises already wrote their own memory.
     *
     * @param  array<string, mixed>  $resolved
     */
    private function rememberPhrase(User $user, array $resolved, Exercise $exercise, ?string $variantLabel, ?string $variantDescription): void
    {
        $rawPhrase = trim((string) ($resolved['raw_phrase'] ?? ''));

        if ($rawPhrase === '') {
            return;
        }

        $confidence = match ((string) $resolved['method']) {
            'evidence', 'resolved' => (float) $resolved['confidence'],
            'exact', 'alias' => 0.95,
            'phrase_memory' => 0.97,
            default => 0.0,
        };

        if ($confidence < 0.80) {
            return;
        }

        $this->upsertPhraseMemory($user, $exercise, $rawPhrase, $variantLabel, $variantDescription, $confidence);
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

    private function visibleExercises(User $user): Builder
    {
        return Exercise::query()
            ->where(function (Builder $builder) use ($user): void {
                $builder->whereNull('user_id')->orWhere('user_id', $user->id);
            });
    }
}
