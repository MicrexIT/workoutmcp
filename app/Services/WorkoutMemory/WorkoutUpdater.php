<?php

namespace App\Services\WorkoutMemory;

use App\Models\Exercise;
use App\Models\User;
use App\Models\WorkoutSession;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\DB;

class WorkoutUpdater
{
    public function __construct(
        private readonly UnitNormalizer $unitNormalizer,
        private readonly TrainingSummaryService $summaries,
        private readonly WorkoutExerciseWriter $exerciseWriter,
    ) {}

    /**
     * @param  array<string, mixed>  $input
     * @return array<string, mixed>
     */
    public function update(User $user, array $input): array
    {
        $session = $this->session($user, (int) ($input['workout_id'] ?? 0));

        if ($session === null) {
            return $this->refused('Workout not found.');
        }

        $operations = $input['operations'] ?? [];
        $confirmedDestructive = (bool) ($input['user_confirmed_destructive_change'] ?? false);

        foreach ($operations as $operation) {
            $type = (string) ($operation['type'] ?? '');

            if (in_array($type, ['remove_exercise', 'remove_set', 'merge_workout'], true) && ! $confirmedDestructive) {
                return $this->refused('Removing exercises or sets, or merging another workout away, requires user_confirmed_destructive_change=true.');
            }

            if ($type === 'merge_workout') {
                $sourceId = (int) ($operation['source_workout_id'] ?? 0);

                if ($sourceId === (int) $session->id) {
                    return $this->refused('A workout cannot be merged into itself.');
                }

                $source = $this->session($user, $sourceId);

                if ($source === null || $source->status === 'deleted') {
                    return $this->refused('merge_workout needs a source_workout_id pointing to an existing workout of this user.');
                }
            }

            if ($type === 'update_exercise' && isset($operation['exercise_id'])) {
                $exists = Exercise::query()
                    ->where(function (Builder $builder) use ($user): void {
                        $builder->whereNull('user_id')->orWhere('user_id', $user->id);
                    })
                    ->whereKey((int) $operation['exercise_id'])
                    ->exists();

                if (! $exists && ExerciseResolver::normalize((string) ($operation['raw_phrase'] ?? '')) === '') {
                    return $this->refused('update_exercise exercise_id does not match any exercise visible to this user. Send the user\'s wording as raw_phrase instead and the server will resolve it.');
                }
            }

            if ($type === 'add_exercise') {
                $validation = $this->exerciseWriter->validateExercise($user, $operation);

                if ($validation !== []) {
                    return $this->refused(
                        'Workout exercise entry is structurally invalid: it needs a raw_phrase or a known exercise_id.',
                        ['unresolved_or_ambiguous_items' => $validation],
                    );
                }
            }

            if ($type === 'reopen_session') {
                if ($session->status !== 'completed') {
                    return $this->refused('Only a completed workout can be reopened.');
                }

                $active = WorkoutSession::query()
                    ->where('user_id', $user->id)
                    ->where('status', 'in_progress')
                    ->first();

                if ($active !== null) {
                    return $this->refused('An in-progress workout session already exists. Finish it before reopening another workout.', [
                        'active_session' => $this->summaries->workout($active),
                    ]);
                }
            }
        }

        $outcomes = [];

        DB::transaction(function () use ($user, $session, $operations, $input, &$outcomes): void {
            $outcomes = [];

            foreach ($operations as $operation) {
                $outcome = $this->applyOperation($user, $session, $operation);

                if ($outcome !== null) {
                    $outcomes[] = $outcome;
                }
            }

            $session->changeEvents()->create([
                'user_id' => $user->id,
                'event_type' => 'updated',
                'reason' => $input['reason'] ?? null,
                'metadata' => ['operations' => $operations],
                'occurred_at' => now(),
            ]);
        }, attempts: 3);

        return [
            'refused' => false,
            'refusal_reason' => null,
            ...$this->exerciseWriter->outcomeSummary($outcomes),
            'updated_workout' => $this->summaries->workout($session->fresh(['exercises.sets', 'exercises.exercise'])),
        ];
    }

    /**
     * @return array<string, mixed>
     */
    public function delete(User $user, int $workoutId, ?string $reason, bool $confirmed): array
    {
        if (! $confirmed) {
            return $this->refused('Deleting a workout requires user_confirmed=true.');
        }

        $session = $this->session($user, $workoutId);

        if ($session === null) {
            return $this->refused('Workout not found.');
        }

        $session->update(['status' => 'deleted']);
        $session->changeEvents()->create([
            'user_id' => $user->id,
            'event_type' => 'deleted',
            'reason' => $reason,
            'metadata' => ['soft_delete' => true],
        ]);
        $session->delete();

        return [
            'refused' => false,
            'refusal_reason' => null,
            'deleted_workout_id' => $session->id,
            'deletion_summary' => [
                'status' => 'deleted',
                'reason' => $reason,
            ],
        ];
    }

    /**
     * @param  array<string, mixed>  $operation
     * @return array<string, mixed>|null resolution outcome for add_exercise operations
     */
    private function applyOperation(User $user, WorkoutSession $session, array $operation): ?array
    {
        $outcome = null;

        switch ((string) ($operation['type'] ?? '')) {
            case 'update_session':
                $session->update(collect($operation)->only([
                    'name',
                    'kind',
                    'notes',
                    'perceived_effort',
                ])->filter(fn (mixed $value): bool => $value !== null)->all());
                break;

            case 'add_exercise':
                $outcome = $this->addExercise($user, $session, $operation);
                break;

            case 'reopen_session':
                $session->update([
                    'status' => 'in_progress',
                    'completed_at' => null,
                ]);
                break;

            case 'update_exercise':
                $outcome = $this->updateExercise($user, $session, $operation);
                break;

            case 'merge_workout':
                $this->mergeWorkout($user, $session, $operation);
                break;

            case 'remove_exercise':
                $this->removeExercise($session, $operation);
                break;

            case 'add_set':
                $this->addSet($session, $operation);
                break;

            case 'update_set':
                $this->updateSet($session, $operation);
                break;

            case 'remove_set':
                $this->removeSet($session, $operation);
                break;
        }

        if (($operation['type'] ?? '') === 'update_session') {
            $this->updateSessionMetrics($session, $operation);
        }

        return $outcome;
    }

    /**
     * @param  array<string, mixed>  $operation
     */
    private function updateSessionMetrics(WorkoutSession $session, array $operation): void
    {
        $updates = [];

        if (isset($operation['occurred_at'])) {
            $updates['started_at'] = $operation['occurred_at'];
        }

        if (array_key_exists('bodyweight_value', $operation)) {
            $updates['bodyweight_kg'] = $this->unitNormalizer->normalizeBodyweight(
                $operation['bodyweight_value'] === null ? null : (float) $operation['bodyweight_value'],
                $operation['bodyweight_unit'] ?? null,
            );
        }

        if ($updates !== []) {
            $session->update($updates);
        }
    }

    /**
     * @param  array<string, mixed>  $operation
     * @return array<string, mixed>
     */
    private function addExercise(User $user, WorkoutSession $session, array $operation): array
    {
        return $this->exerciseWriter->createWorkoutExercise(
            user: $user,
            session: $session,
            exerciseInput: $operation,
            sortOrder: isset($operation['sort_order']) ? (int) $operation['sort_order'] : null,
        )['outcome'];
    }

    /**
     * Edit an entry's annotations, and correct its exercise mapping by
     * raw_phrase (resolved through the same server-side ladder as logging,
     * never a silent no-op) or explicit exercise_id. With remember_phrase=true
     * the corrected mapping is stored as phrase memory so the same wording
     * resolves right next time.
     *
     * @param  array<string, mixed>  $operation
     * @return array<string, mixed>|null correction outcome when the exercise mapping was addressed
     */
    private function updateExercise(User $user, WorkoutSession $session, array $operation): ?array
    {
        $workoutExercise = $session->exercises()->findOrFail($operation['workout_exercise_id'] ?? null);
        $updates = collect($operation)->only([
            'notes',
            'variant_label',
            'variant_description',
        ])->all();
        $outcome = null;

        $hasCorrection = isset($operation['exercise_id'])
            || ExerciseResolver::normalize((string) ($operation['raw_phrase'] ?? '')) !== '';

        if ($hasCorrection) {
            ['exercise' => $exercise, 'outcome' => $outcome] = $this->exerciseWriter->resolveCorrection($user, [
                ...$operation,
                'sets' => $workoutExercise->sets
                    ->map(fn ($set): array => array_filter([
                        'reps' => $set->reps,
                        'load_value' => $set->load_kg,
                        'duration_seconds' => $set->duration_seconds,
                        'distance_value' => $set->distance_meters,
                    ], fn (mixed $value): bool => $value !== null))
                    ->all(),
            ]);

            $unchanged = (int) $exercise->id === (int) $workoutExercise->exercise_id;

            if (! $unchanged) {
                $updates = [
                    ...$updates,
                    'exercise_id' => $exercise->id,
                    'name_snapshot' => $exercise->name,
                    'tracking_mode_snapshot' => $exercise->tracking_mode,
                    'resolution_type' => 'manual_correction',
                ];
            }

            $outcome = [
                ...$outcome,
                'corrected_workout_exercise_id' => $workoutExercise->id,
                'unchanged' => $unchanged,
            ];

            if ($unchanged) {
                $outcome['hint'] = 'The correction resolved to the exercise already on the entry. If the user means a different exercise, find it with search_exercises and retry with its exercise_id, or create it with create_exercise first.';
            }

            $rememberPhrase = trim((string) ($operation['raw_phrase'] ?? ''));
            $rememberPhrase = $rememberPhrase !== '' ? $rememberPhrase : trim((string) $workoutExercise->raw_phrase);

            if ((bool) ($operation['remember_phrase'] ?? false) && $rememberPhrase !== '') {
                $this->exerciseWriter->upsertPhraseMemory($user, $exercise, $rememberPhrase);
            }
        }

        $workoutExercise->update($updates);

        return $outcome;
    }

    /**
     * Absorb another workout's exercises into this one (continuing the sort
     * order), widen the time span, then soft-delete the emptied source. Used
     * when one real session was split across two logged workouts.
     *
     * @param  array<string, mixed>  $operation
     */
    private function mergeWorkout(User $user, WorkoutSession $session, array $operation): void
    {
        /** @var WorkoutSession $source */
        $source = WorkoutSession::query()
            ->where('user_id', $user->id)
            ->whereKey((int) ($operation['source_workout_id'] ?? 0))
            ->lockForUpdate()
            ->firstOrFail();

        $sortOrder = (int) ($session->exercises()->max('sort_order') ?? 0);

        foreach ($source->exercises()->orderBy('sort_order')->orderBy('id')->get() as $exercise) {
            $exercise->update([
                'workout_session_id' => $session->id,
                'sort_order' => ++$sortOrder,
            ]);
        }

        $timing = [];

        if ($source->started_at !== null && ($session->started_at === null || $source->started_at->lt($session->started_at))) {
            $timing['started_at'] = $source->started_at;
        }

        if ($session->completed_at !== null && $source->completed_at !== null && $source->completed_at->gt($session->completed_at)) {
            $timing['completed_at'] = $source->completed_at;
        }

        if ($timing !== []) {
            $session->update($timing);
        }

        $source->changeEvents()->create([
            'user_id' => $user->id,
            'event_type' => 'merged',
            'reason' => $operation['reason'] ?? null,
            'metadata' => ['merged_into_workout_id' => $session->id],
            'occurred_at' => now(),
        ]);
        $source->update(['status' => 'deleted']);
        $source->delete();
    }

    /**
     * @param  array<string, mixed>  $operation
     */
    private function removeExercise(WorkoutSession $session, array $operation): void
    {
        $session->exercises()->findOrFail($operation['workout_exercise_id'] ?? null)->delete();
    }

    /**
     * @param  array<string, mixed>  $operation
     */
    private function addSet(WorkoutSession $session, array $operation): void
    {
        $workoutExercise = $session->exercises()->findOrFail($operation['workout_exercise_id'] ?? null);
        $this->exerciseWriter->createSet($workoutExercise, $operation);
    }

    /**
     * @param  array<string, mixed>  $operation
     */
    private function updateSet(WorkoutSession $session, array $operation): void
    {
        $set = $session->exercises()
            ->whereHas('sets', fn (Builder $builder) => $builder->whereKey($operation['workout_set_id'] ?? null))
            ->firstOrFail()
            ->sets()
            ->findOrFail($operation['workout_set_id']);

        $set->update($this->exerciseWriter->setPayload($operation));
    }

    /**
     * @param  array<string, mixed>  $operation
     */
    private function removeSet(WorkoutSession $session, array $operation): void
    {
        $session->exercises()
            ->whereHas('sets', fn (Builder $builder) => $builder->whereKey($operation['workout_set_id'] ?? null))
            ->firstOrFail()
            ->sets()
            ->findOrFail($operation['workout_set_id'])
            ->delete();
    }

    private function session(User $user, int $workoutId): ?WorkoutSession
    {
        return WorkoutSession::withTrashed()
            ->where('user_id', $user->id)
            ->whereKey($workoutId)
            ->first();
    }

    /**
     * @return array<string, mixed>
     */
    private function refused(string $reason, array $extra = []): array
    {
        return [
            'refused' => true,
            'refusal_reason' => $reason,
            'updated_workout' => null,
            ...$extra,
        ];
    }
}
