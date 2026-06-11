<?php

namespace App\Services\WorkoutMemory;

use App\Models\Exercise;
use App\Models\ExercisePhraseMemory;
use App\Models\ExerciseResolutionAttempt;
use App\Models\User;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Collection;
use Illuminate\Support\Str;
use Throwable;

class ExerciseResolver
{
    public function __construct(private readonly ExerciseSerializer $serializer) {}

    /**
     * @param  list<array<string, mixed>>  $mentions
     * @return list<array<string, mixed>>
     */
    public function resolveMentions(User $user, array $mentions, ?string $workoutKind = null, bool $allowBucketSuggestions = true): array
    {
        return collect($mentions)
            ->map(fn (array $mention): array => $this->resolveOne(
                user: $user,
                rawPhrase: (string) ($mention['raw_phrase'] ?? ''),
                context: $mention['context'] ?? null,
                workoutKind: $workoutKind,
                allowBucketSuggestions: $allowBucketSuggestions,
            ))
            ->values()
            ->all();
    }

    /**
     * @param  array<string, mixed>  $filters
     * @return array<string, mixed>
     */
    public function search(User $user, array $filters, bool $persistEvidence = true): array
    {
        $query = trim((string) ($filters['query'] ?? ''));
        $normalized = self::normalize($query);

        $exercises = $this->visibleExercises($user)
            ->with(['aliases', 'parent'])
            ->when($filters['category'] ?? null, fn (Builder $builder, string $category) => $builder->where('category', $category))
            ->when($filters['granularity'] ?? null, fn (Builder $builder, string $granularity) => $builder->where('granularity', $granularity))
            ->when($filters['tracking_mode'] ?? null, fn (Builder $builder, string $trackingMode) => $builder->where('tracking_mode', $trackingMode))
            ->get();

        $candidates = $this->rankCandidates($user, $normalized, $query, $exercises, $filters)
            ->take((int) ($filters['limit'] ?? 10))
            ->values()
            ->all();

        $best = $candidates[0] ?? null;
        $attempt = null;

        $attemptResult = ['attempt' => null, 'warning' => null];

        if ($persistEvidence && $query !== '') {
            $attemptResult = $this->tryStoreAttempt(
                user: $user,
                rawPhrase: $query,
                normalizedPhrase: $normalized,
                context: null,
                candidates: $candidates,
                best: $best,
                suggestedAction: $this->suggestedAction($best, null),
            );
            $attempt = $attemptResult['attempt'];
        }

        return [
            'resolution_id' => $attempt?->resolution_id,
            'query' => $query,
            'matches' => array_map(fn (array $candidate): array => $candidate['exercise'], $candidates),
            'candidates' => $candidates,
            'creating_new_exercise_recommended' => $best === null || $best['confidence'] < 0.60,
            'duplicate_risk' => $this->duplicateRisk($best),
            'evidence_persisted' => $attempt !== null,
            'evidence_persistence_warning' => $attemptResult['warning'],
        ];
    }

    /**
     * Resolve one raw phrase on behalf of a logging tool, persisting discovery evidence.
     *
     * @return array<string, mixed>
     */
    public function resolveForLogging(User $user, string $rawPhrase, ?string $context = null, ?string $workoutKind = null): array
    {
        return $this->resolveOne(
            user: $user,
            rawPhrase: $rawPhrase,
            context: $context,
            workoutKind: $workoutKind,
            allowBucketSuggestions: true,
        );
    }

    public static function normalize(string $value): string
    {
        $normalized = Str::of($value)
            ->ascii()
            ->lower()
            ->replaceMatches('/\bhspu\b/', 'handstand push up')
            ->replaceMatches('/\bhs\b/', 'handstand')
            ->replaceMatches('/\bmu\b/', 'muscle up')
            ->replaceMatches('/\brto\b/', 'ring turned out')
            ->replaceMatches('/\bdb\b/', 'dumbbell')
            ->replaceMatches('/\bkb\b/', 'kettlebell')
            ->replaceMatches('/\bc2b\b/', 'chest to bar')
            ->replaceMatches('/\bttb\b/', 'toes to bar')
            ->replaceMatches('/[^a-z0-9+]+/', ' ')
            ->replaceMatches('/\s+/', ' ')
            ->trim();

        return (string) $normalized;
    }

    private function resolveOne(
        User $user,
        string $rawPhrase,
        mixed $context,
        ?string $workoutKind,
        bool $allowBucketSuggestions,
    ): array {
        $rawPhrase = trim($rawPhrase);
        $normalized = self::normalize($rawPhrase);

        $memory = ExercisePhraseMemory::query()
            ->where('user_id', $user->id)
            ->where('normalized_phrase', $normalized)
            ->with('exercise.aliases', 'exercise.parent')
            ->first();

        $exercises = $this->visibleExercises($user)->with(['aliases', 'parent'])->get();
        $candidates = $this->rankCandidates($user, $normalized, $rawPhrase, $exercises, [
            'workout_kind' => $workoutKind,
        ]);

        $memoryCandidate = null;

        if ($memory?->exercise !== null) {
            $memoryCandidate = $this->candidate($memory->exercise, 'phrase_memory', 0.97, 'User phrase memory matched this wording.', $user);
            $candidates = collect([$memoryCandidate, ...$candidates])
                ->unique(fn (array $candidate): int => (int) $candidate['exercise']['id'])
                ->sortByDesc('confidence')
                ->values();
        }

        $bucketCandidate = $allowBucketSuggestions
            ? $this->bucketSuggestion($user, $normalized, $rawPhrase)
            : null;

        $best = $candidates->first();
        $suggestedAction = $this->suggestedAction($best, $bucketCandidate);
        $resolution = $best['resolution'] ?? 'create_suggestion';

        if ($bucketCandidate !== null && ($best === null || $best['confidence'] < 0.70)) {
            $resolution = 'bucket_suggestion';
            $suggestedAction = 'use_bucket';
        }

        if ($best !== null && $best['confidence'] < 0.60 && $bucketCandidate === null) {
            $resolution = 'create_suggestion';
            $suggestedAction = 'create_new';
        }

        if ($best !== null && $best['confidence'] >= 0.60 && $best['confidence'] < 0.80 && $bucketCandidate === null) {
            $resolution = 'ambiguous';
            $suggestedAction = 'ask_user';
        }

        $topCandidates = $candidates->take(8);

        if ($bucketCandidate !== null) {
            $topCandidates = $topCandidates
                ->push($bucketCandidate)
                ->sortByDesc('confidence')
                ->unique(fn (array $candidate): int => (int) $candidate['exercise']['id']);
        }

        $topCandidates = $topCandidates->values()->all();

        $usesPhraseMemory = ($best['resolution'] ?? null) === 'phrase_memory';

        $attemptResult = $this->tryStoreAttempt(
            user: $user,
            rawPhrase: $rawPhrase,
            normalizedPhrase: $normalized,
            context: is_string($context) ? $context : null,
            candidates: $topCandidates,
            best: $resolution === 'bucket_suggestion' ? $bucketCandidate : $best,
            suggestedAction: $suggestedAction,
        );
        $attempt = $attemptResult['attempt'];

        $chosenExercise = $resolution === 'bucket_suggestion'
            ? ($bucketCandidate['exercise'] ?? null)
            : (($best !== null && $best['confidence'] >= 0.80) ? $best['exercise'] : null);
        $variantLabel = $usesPhraseMemory ? $memory?->variant_label : null;

        return [
            'resolution_id' => $attempt?->resolution_id,
            'raw_phrase' => $rawPhrase,
            'resolution' => $resolution,
            'resolution_type' => $resolution,
            'exercise' => ($best !== null && $best['confidence'] >= 0.80) ? $best['exercise'] : null,
            'variant_label' => $variantLabel,
            'variant_description' => $usesPhraseMemory ? $memory?->variant_description : null,
            'confidence' => $resolution === 'bucket_suggestion' ? $bucketCandidate['confidence'] : (float) ($best['confidence'] ?? 0),
            'duplicate_risk' => $this->duplicateRisk($best),
            'should_create' => $suggestedAction === 'create_new',
            'should_ask_user' => $suggestedAction === 'ask_user',
            'suggested_action' => $suggestedAction,
            'recommended_bucket_exercise' => $bucketCandidate['exercise'] ?? null,
            'requires_variant_details' => ($chosenExercise['granularity'] ?? null) === 'bucket',
            'evidence_persisted' => $attempt !== null,
            'evidence_persistence_warning' => $attemptResult['warning'],
            'log_entry_template' => [
                'raw_phrase' => $rawPhrase,
                'exercise_id' => $chosenExercise['id'] ?? null,
                'resolution_id' => $attempt?->resolution_id,
                'variant_label' => $variantLabel,
            ],
            'candidates' => $topCandidates,
        ];
    }

    /**
     * @param  Collection<int, Exercise>  $exercises
     * @param  array<string, mixed>  $filters
     * @return Collection<int, array<string, mixed>>
     */
    private function rankCandidates(User $user, string $normalized, string $rawPhrase, Collection $exercises, array $filters): Collection
    {
        $equipment = collect($filters['equipment'] ?? [])->filter()->map(fn (mixed $value): string => self::normalize((string) $value));
        $muscle = self::normalize((string) ($filters['muscle'] ?? ''));
        $tags = collect($filters['tags'] ?? [])->filter()->map(fn (mixed $value): string => self::normalize((string) $value));
        $searchPhrases = $this->searchPhrases($normalized);

        return $exercises
            ->map(function (Exercise $exercise) use ($user, $normalized, $rawPhrase, $equipment, $muscle, $tags, $searchPhrases): ?array {
                $name = self::normalize($exercise->name);
                $aliasMatches = $exercise->aliases
                    ->map(fn ($alias): string => $alias->normalized_alias)
                    ->filter()
                    ->values();

                $reason = 'Fuzzy catalog match.';
                $resolution = 'variant';
                $score = 0.0;

                if ($normalized !== '' && $searchPhrases->contains($name)) {
                    $score = 1.0;
                    $resolution = 'exact';
                    $reason = 'Exact exercise name match.';
                }

                if ($score < 0.98 && $aliasMatches->intersect($searchPhrases)->isNotEmpty()) {
                    $score = 0.98;
                    $resolution = 'alias';
                    $reason = 'Exact alias match.';
                }

                if ($normalized !== '' && $score < 0.98) {
                    $aliasScore = $aliasMatches
                        ->flatMap(fn (string $alias): array => $searchPhrases
                            ->map(fn (string $phrase): float => $this->textScore($phrase, $alias))
                            ->all())
                        ->max() ?? 0.0;
                    $nameScore = $searchPhrases
                        ->map(fn (string $phrase): float => $this->textScore($phrase, $name))
                        ->max() ?? 0.0;
                    $score = max($score, $aliasScore, $nameScore);
                }

                if ($score < 0.94 && $this->looksWeighted($rawPhrase) && str_starts_with($name, 'weighted ')) {
                    $unweightedName = trim(substr($name, strlen('weighted ')));
                    $weightedScore = $searchPhrases
                        ->map(fn (string $phrase): float => $this->textScore($phrase, $unweightedName) + 0.12)
                        ->max() ?? 0.0;
                    $score = max($score, $weightedScore);
                    $reason = 'Load in phrase points to the weighted variant.';
                }

                if ($equipment->isNotEmpty()) {
                    $exerciseEquipment = collect($exercise->equipment ?? [])->map(fn (string $value): string => self::normalize($value));
                    $score += $equipment->intersect($exerciseEquipment)->isNotEmpty() ? 0.08 : -0.05;
                }

                if ($muscle !== '') {
                    $exerciseMuscles = collect($exercise->primary_muscles ?? [])->map(fn (string $value): string => self::normalize($value));
                    $score += $exerciseMuscles->contains($muscle) ? 0.08 : 0;
                }

                if ($tags->isNotEmpty()) {
                    $exerciseTags = collect($exercise->tags ?? [])->map(fn (string $value): string => self::normalize($value));
                    $score += $tags->intersect($exerciseTags)->isNotEmpty() ? 0.05 : 0;
                }

                $score = min(1.0, max(0.0, round($score, 2)));

                if ($score < 0.35 && $normalized !== '') {
                    return null;
                }

                return $this->candidate($exercise, $resolution, $score, $reason, $user);
            })
            ->filter()
            ->sortByDesc('confidence')
            ->values();
    }

    private function textScore(string $needle, string $candidate): float
    {
        if ($needle === '' || $candidate === '') {
            return 0.0;
        }

        if ($needle === $candidate) {
            return 1.0;
        }

        if (str_contains($candidate, $needle) || str_contains($needle, $candidate)) {
            return 0.86;
        }

        $needleTokens = collect(explode(' ', $needle))->filter()->values();
        $candidateTokens = collect(explode(' ', $candidate))->filter()->values();
        $intersection = $needleTokens->intersect($candidateTokens)->count();
        $union = max(1, $needleTokens->merge($candidateTokens)->unique()->count());
        $tokenScore = $intersection / $union;

        similar_text($needle, $candidate, $similarity);

        return round(max($tokenScore, $similarity / 100), 2);
    }

    private function looksWeighted(string $rawPhrase): bool
    {
        return str_contains(self::normalize($rawPhrase), 'weighted')
            || preg_match('/\+\s*\d+(?:\.\d+)?\s*(kg|lb|lbs)\b/i', $rawPhrase) === 1;
    }

    /**
     * @return Collection<int, string>
     */
    private function searchPhrases(string $normalized): Collection
    {
        if ($normalized === '') {
            return collect(['']);
        }

        $withoutLoads = (string) Str::of($normalized)
            ->replaceMatches('/\b(?:at|with|using|plus)?\s*\+?\d+(?:\.\d+)?\s*(?:kg|kgs|kilogram|kilograms|lb|lbs|pound|pounds)\b/', ' ')
            ->replaceMatches('/\b(?:at|with|using|plus)\s*$/', ' ')
            ->replaceMatches('/\s+/', ' ')
            ->trim();

        $singularized = (string) Str::of($withoutLoads)
            ->replaceMatches('/\bpistols\b/', 'pistol')
            ->replaceMatches('/\bsquats\b/', 'squat')
            ->trim();

        return collect([$normalized, $withoutLoads, $singularized])
            ->filter(fn (string $phrase): bool => $phrase !== '')
            ->unique()
            ->values();
    }

    /**
     * @return array<string, mixed>|null
     */
    private function bucketSuggestion(User $user, string $normalized, string $rawPhrase): ?array
    {
        $bucketName = match (true) {
            str_contains($normalized, 'handstand') || str_contains($normalized, 'wall') => 'Handstand Accessory Drill',
            str_contains($normalized, 'compression') || str_contains($normalized, 'pancake') || str_contains($normalized, 'pike') => 'Compression Drill',
            str_contains($normalized, 'wrist') => 'Wrist Prep',
            str_contains($normalized, 'mobility') || str_contains($normalized, 'stretch') || str_contains($normalized, 'prep') => 'Mobility Flow',
            str_contains($normalized, 'rings') || str_contains($normalized, 'ring') => 'Rings Skill Drill',
            strlen($rawPhrase) > 45 => 'Skill Practice Block',
            default => null,
        };

        if ($bucketName === null) {
            return null;
        }

        $bucket = $this->visibleExercises($user)
            ->where('granularity', 'bucket')
            ->where('normalized_name', self::normalize($bucketName))
            ->with(['aliases', 'parent'])
            ->first();

        return $bucket ? $this->candidate($bucket, 'bucket_suggestion', 0.72, 'Broad bucket is safer than creating a tiny one-off exercise.', $user) : null;
    }

    /**
     * @return array<string, mixed>
     */
    private function candidate(Exercise $exercise, string $resolution, float $confidence, string $whyMatched, User $user): array
    {
        $usage = $exercise->workoutExercises()
            ->whereHas('session', fn (Builder $builder) => $builder->where('user_id', $user->id)->where('status', '!=', 'deleted'))
            ->selectRaw('count(*) as usage_count, max(created_at) as last_used_at')
            ->first();

        return [
            'resolution' => $resolution,
            'confidence' => $confidence,
            'why_matched' => $whyMatched,
            'exercise' => $this->serializer->summary(
                $exercise,
                (int) ($usage?->usage_count ?? 0),
                $usage?->last_used_at,
            ),
        ];
    }

    private function suggestedAction(?array $best, ?array $bucketCandidate): string
    {
        if ($best !== null && $best['confidence'] >= 0.80) {
            return 'use_existing';
        }

        if ($bucketCandidate !== null) {
            return 'use_bucket';
        }

        if ($best !== null && $best['confidence'] >= 0.60) {
            return 'ask_user';
        }

        return 'create_new';
    }

    private function duplicateRisk(?array $best): string
    {
        if ($best === null) {
            return 'low';
        }

        return match (true) {
            $best['confidence'] >= 0.80 => 'high',
            $best['confidence'] >= 0.60 => 'medium',
            default => 'low',
        };
    }

    /**
     * @param  list<array<string, mixed>>  $candidates
     */
    private function storeAttempt(
        User $user,
        string $rawPhrase,
        string $normalizedPhrase,
        ?string $context,
        array $candidates,
        ?array $best,
        string $suggestedAction,
    ): ExerciseResolutionAttempt {
        return ExerciseResolutionAttempt::query()->create([
            'resolution_id' => (string) Str::uuid(),
            'user_id' => $user->id,
            'raw_phrase' => $rawPhrase,
            'normalized_phrase' => $normalizedPhrase,
            'context' => $context,
            'best_resolution' => $best['resolution'] ?? 'create_suggestion',
            'best_exercise_id' => $best['exercise']['id'] ?? null,
            'best_confidence' => $best['confidence'] ?? 0,
            'duplicate_risk' => $this->duplicateRisk($best),
            'candidates' => $candidates,
            'suggested_action' => $suggestedAction,
            'expires_at' => now()->addDay(),
        ]);
    }

    /**
     * Discovery evidence is helpful, but logging should not fail when SQLite is
     * temporarily locked or the evidence table is unavailable.
     *
     * @param  list<array<string, mixed>>  $candidates
     * @return array{attempt: ExerciseResolutionAttempt|null, warning: string|null}
     */
    private function tryStoreAttempt(
        User $user,
        string $rawPhrase,
        string $normalizedPhrase,
        ?string $context,
        array $candidates,
        ?array $best,
        string $suggestedAction,
    ): array {
        try {
            return [
                'attempt' => $this->storeAttempt(
                    user: $user,
                    rawPhrase: $rawPhrase,
                    normalizedPhrase: $normalizedPhrase,
                    context: $context,
                    candidates: $candidates,
                    best: $best,
                    suggestedAction: $suggestedAction,
                ),
                'warning' => null,
            ];
        } catch (Throwable $exception) {
            report($exception);

            return [
                'attempt' => null,
                'warning' => 'Resolution evidence could not be persisted. Continue with raw_phrase; log_workout and append_workout_exercise can resolve server-side.',
            ];
        }
    }

    private function visibleExercises(User $user): Builder
    {
        return Exercise::query()
            ->where(function (Builder $builder) use ($user): void {
                $builder->whereNull('user_id')->orWhere('user_id', $user->id);
            });
    }
}
