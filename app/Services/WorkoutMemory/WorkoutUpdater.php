<?php

namespace App\Services\WorkoutMemory;

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

            if (in_array($type, ['remove_exercise', 'remove_set'], true) && ! $confirmedDestructive) {
                return $this->refused('Removing exercises or sets requires user_confirmed_destructive_change=true.');
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

            case 'update_exercise':
                $this->updateExercise($session, $operation);
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
     * @param  array<string, mixed>  $operation
     */
    private function updateExercise(WorkoutSession $session, array $operation): void
    {
        $workoutExercise = $session->exercises()->findOrFail($operation['workout_exercise_id'] ?? null);
        $workoutExercise->update(collect($operation)->only([
            'notes',
            'variant_label',
            'variant_description',
        ])->all());
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
