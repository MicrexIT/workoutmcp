<?php

namespace App\Services\WorkoutMemory;

use App\Models\Exercise;
use App\Models\User;
use App\Models\WorkoutExercise;
use App\Models\WorkoutSession;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Carbon;

class TrainingSummaryService
{
    /**
     * @return array<string, mixed>
     */
    public function workout(WorkoutSession $session): array
    {
        $session->loadMissing(['exercises.exercise.aliases', 'exercises.sets', 'changeEvents']);

        return [
            'id' => $session->id,
            'name' => $session->name,
            'kind' => $session->kind,
            'status' => $session->status,
            'source' => $session->source,
            'started_at' => $session->started_at?->toISOString(),
            'completed_at' => $session->completed_at?->toISOString(),
            'timezone' => $session->occurred_timezone,
            'perceived_effort' => $session->perceived_effort,
            'bodyweight_kg' => $session->bodyweight_kg,
            'notes' => $session->notes,
            'raw_input' => $session->raw_input,
            'events' => $session->changeEvents
                ->sortBy(fn ($event) => $event->occurred_at ?? $event->created_at)
                ->map(fn ($event): array => [
                    'id' => $event->id,
                    'event_type' => $event->event_type,
                    'reason' => $event->reason,
                    'occurred_at' => $event->occurred_at?->toISOString(),
                    'metadata' => $event->metadata,
                ])
                ->values()
                ->all(),
            'exercises' => $session->exercises
                ->sortBy('sort_order')
                ->map(fn (WorkoutExercise $exercise): array => [
                    'id' => $exercise->id,
                    'exercise_id' => $exercise->exercise_id,
                    'sort_order' => $exercise->sort_order,
                    'name' => $exercise->name_snapshot,
                    'tracking_mode' => $exercise->tracking_mode_snapshot,
                    'raw_phrase' => $exercise->raw_phrase,
                    'resolution_type' => $exercise->resolution_type,
                    'variant_label' => $exercise->variant_label,
                    'variant_description' => $exercise->variant_description,
                    'notes' => $exercise->notes,
                    'sets' => $exercise->sets->map(fn ($set): array => [
                        'id' => $set->id,
                        'set_number' => $set->set_number,
                        'reps' => $set->reps,
                        'load_kg' => $set->load_kg,
                        'load_type' => $set->load_type,
                        'duration_seconds' => $set->duration_seconds,
                        'distance_meters' => $set->distance_meters,
                        'rpe' => $set->rpe,
                        'rir' => $set->rir,
                        'side' => $set->side,
                        'success' => $set->success,
                        'quality_rating' => $set->quality_rating,
                        'is_warmup' => $set->is_warmup,
                        'raw_set_text' => $set->raw_set_text,
                        'custom_metrics' => $set->custom_metrics,
                        'notes' => $set->notes,
                    ])->values()->all(),
                ])->values()->all(),
            'set_count' => $session->exercises->flatMap->sets->count(),
        ];
    }

    /**
     * @return list<array<string, mixed>>
     */
    public function recentWorkouts(User $user, int $limit = 10, ?string $since = null, ?string $kind = null): array
    {
        return $this->sessionQuery($user, $since, $kind)
            ->with(['exercises.exercise', 'exercises.sets'])
            ->latest('started_at')
            ->limit($limit)
            ->get()
            ->map(fn (WorkoutSession $session): array => [
                'id' => $session->id,
                'name' => $session->name,
                'kind' => $session->kind,
                'started_at' => $session->started_at?->toISOString(),
                'completed_at' => $session->completed_at?->toISOString(),
                'exercise_names' => $session->exercises->sortBy('sort_order')->pluck('name_snapshot')->values()->all(),
                'set_count' => $session->exercises->flatMap->sets->count(),
                'top_level_volume_signals' => [
                    'loaded_reps' => $session->exercises->flatMap->sets->sum(fn ($set): int => (int) ($set->load_kg !== null ? $set->reps : 0)),
                    'bodyweight_reps' => $session->exercises->flatMap->sets->sum(fn ($set): int => (int) ($set->load_kg === null ? $set->reps : 0)),
                    'duration_seconds' => $session->exercises->flatMap->sets->sum('duration_seconds'),
                    'distance_meters' => round((float) $session->exercises->flatMap->sets->sum('distance_meters'), 2),
                ],
            ])
            ->values()
            ->all();
    }

    /**
     * @return array<string, mixed>|null
     */
    public function getWorkout(User $user, int $workoutId): ?array
    {
        $session = WorkoutSession::query()
            ->where('user_id', $user->id)
            ->whereKey($workoutId)
            ->first();

        return $session ? $this->workout($session) : null;
    }

    /**
     * @return array<string, mixed>
     */
    public function exerciseHistory(User $user, int $exerciseId, ?string $variantLabel = null, ?string $since = null, int $limit = 10): array
    {
        $exercise = Exercise::query()
            ->where(fn (Builder $builder) => $builder->whereNull('user_id')->orWhere('user_id', $user->id))
            ->whereKey($exerciseId)
            ->firstOrFail();

        $query = WorkoutExercise::query()
            ->where('exercise_id', $exercise->id)
            ->whereHas('session', function (Builder $builder) use ($user, $since): void {
                $builder->where('user_id', $user->id)->where('status', '!=', 'deleted');

                if ($since !== null) {
                    $builder->where('started_at', '>=', Carbon::parse($since));
                }
            })
            ->when($variantLabel !== null, fn (Builder $builder) => $builder->where('variant_label', $variantLabel))
            ->with(['session', 'sets'])
            ->latest();

        $performances = $query->limit($limit)->get();
        $allSets = $performances->flatMap->sets;
        $bestSet = $allSets
            ->sortByDesc(fn ($set): float => (float) (($set->load_kg ?? 0) * 1000 + ($set->reps ?? 0) + (($set->duration_seconds ?? 0) / 1000)))
            ->first();

        return [
            'exercise' => [
                'id' => $exercise->id,
                'name' => $exercise->name,
                'granularity' => $exercise->granularity,
            ],
            'variant_label' => $variantLabel,
            'last_performed_at' => $performances->first()?->session?->started_at?->toISOString(),
            'recent_performances' => $performances->map(fn (WorkoutExercise $workoutExercise): array => [
                'workout_id' => $workoutExercise->workout_session_id,
                'workout_name' => $workoutExercise->session->name,
                'started_at' => $workoutExercise->session->started_at?->toISOString(),
                'variant_label' => $workoutExercise->variant_label,
                'variant_description' => $workoutExercise->variant_description,
                'notes' => $workoutExercise->notes,
                'sets' => $workoutExercise->sets->map(fn ($set): array => [
                    'set_number' => $set->set_number,
                    'reps' => $set->reps,
                    'load_kg' => $set->load_kg,
                    'duration_seconds' => $set->duration_seconds,
                    'distance_meters' => $set->distance_meters,
                    'rpe' => $set->rpe,
                    'quality_rating' => $set->quality_rating,
                ])->values()->all(),
            ])->values()->all(),
            'best_set' => $bestSet ? [
                'reps' => $bestSet->reps,
                'load_kg' => $bestSet->load_kg,
                'duration_seconds' => $bestSet->duration_seconds,
                'distance_meters' => $bestSet->distance_meters,
            ] : null,
            'estimated_volume' => [
                'loaded_volume_kg_reps' => round((float) $allSets->sum(fn ($set): float => (float) ($set->load_kg ?? 0) * (int) ($set->reps ?? 0)), 2),
                'total_reps' => (int) $allSets->sum('reps'),
                'total_duration_seconds' => (int) $allSets->sum('duration_seconds'),
                'total_distance_meters' => round((float) $allSets->sum('distance_meters'), 2),
            ],
            'trend_hints' => $this->trendHints($performances),
            'matching_bucketed_variants' => $exercise->granularity === 'bucket'
                ? $performances->pluck('variant_label')->filter()->unique()->values()->all()
                : [],
        ];
    }

    /**
     * @return array<string, mixed>
     */
    public function trainingSummary(User $user, ?string $since = null, ?string $focus = null, bool $includeBuckets = true): array
    {
        $sinceDate = $since ? Carbon::parse($since) : now()->subDays(30);
        $sessions = $this->sessionQuery($user, $sinceDate->toISOString(), null)
            ->with(['exercises.exercise', 'exercises.sets'])
            ->get();

        $workoutExercises = $sessions->flatMap->exercises;
        $exerciseModels = $workoutExercises->pluck('exercise')->filter();

        if (! $includeBuckets) {
            $workoutExercises = $workoutExercises->filter(fn (WorkoutExercise $exercise): bool => $exercise->exercise?->granularity !== 'bucket');
        }

        return [
            'since' => $sinceDate->toISOString(),
            'focus' => $focus,
            'recent_frequency' => [
                'session_count' => $sessions->count(),
                'by_kind' => $sessions->groupBy('kind')->map->count()->all(),
                'last_session_at' => $sessions->sortByDesc('started_at')->first()?->started_at?->toISOString(),
            ],
            'muscle_exposure' => $exerciseModels
                ->flatMap(fn (Exercise $exercise): array => $exercise->primary_muscles ?? [])
                ->countBy()
                ->sortDesc()
                ->all(),
            'equipment_exposure' => $exerciseModels
                ->flatMap(fn (Exercise $exercise): array => $exercise->equipment ?? [])
                ->countBy()
                ->sortDesc()
                ->all(),
            'notable_gaps' => $this->notableGaps($sessions),
            'recent_hard_sessions' => $sessions
                ->filter(fn (WorkoutSession $session): bool => (int) $session->perceived_effort >= 8)
                ->sortByDesc('started_at')
                ->take(5)
                ->map(fn (WorkoutSession $session): array => [
                    'id' => $session->id,
                    'name' => $session->name,
                    'kind' => $session->kind,
                    'started_at' => $session->started_at?->toISOString(),
                    'perceived_effort' => $session->perceived_effort,
                ])
                ->values()
                ->all(),
            'exercises_to_avoid_repeating_too_soon' => $sessions
                ->filter(fn (WorkoutSession $session): bool => $session->started_at?->gte(now()->subDays(3)) ?? false)
                ->flatMap->exercises
                ->pluck('name_snapshot')
                ->unique()
                ->values()
                ->all(),
            'recent_skill_practice_and_bucketed_drill_notes' => $workoutExercises
                ->filter(fn (WorkoutExercise $exercise): bool => $exercise->exercise?->granularity === 'bucket' || in_array($exercise->exercise?->category, ['handstand', 'compression', 'rings'], true))
                ->take(12)
                ->map(fn (WorkoutExercise $exercise): array => [
                    'name' => $exercise->name_snapshot,
                    'variant_label' => $exercise->variant_label,
                    'variant_description' => $exercise->variant_description,
                    'notes' => $exercise->notes,
                ])
                ->values()
                ->all(),
        ];
    }

    private function sessionQuery(User $user, ?string $since = null, ?string $kind = null): Builder
    {
        return WorkoutSession::query()
            ->where('user_id', $user->id)
            ->where('status', '!=', 'deleted')
            ->when($since !== null, fn (Builder $builder) => $builder->where('started_at', '>=', Carbon::parse($since)))
            ->when($kind !== null, fn (Builder $builder) => $builder->where('kind', $kind));
    }

    /**
     * @param  \Illuminate\Support\Collection<int, WorkoutExercise>  $performances
     * @return list<string>
     */
    private function trendHints($performances): array
    {
        if ($performances->count() < 2) {
            return ['Need more logged sessions before trend is meaningful.'];
        }

        $latest = $performances->first()->sets->max('load_kg') ?? $performances->first()->sets->sum('reps');
        $previous = $performances->skip(1)->first()->sets->max('load_kg') ?? $performances->skip(1)->first()->sets->sum('reps');

        if ($latest > $previous) {
            return ['Latest performance is above the previous logged exposure on the main metric.'];
        }

        if ($latest < $previous) {
            return ['Latest performance is below the previous logged exposure; check context before interpreting as regression.'];
        }

        return ['Recent logged exposure is broadly stable.'];
    }

    /**
     * @param  \Illuminate\Support\Collection<int, WorkoutSession>  $sessions
     * @return list<string>
     */
    private function notableGaps($sessions): array
    {
        $kinds = $sessions->pluck('kind')->unique();
        $gaps = [];

        foreach (['strength', 'conditioning', 'mobility'] as $kind) {
            if (! $kinds->contains($kind)) {
                $gaps[] = "No {$kind} sessions logged in this window.";
            }
        }

        return $gaps;
    }
}
