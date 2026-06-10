<?php

namespace App\Services\WorkoutMemory;

use App\Models\User;
use App\Models\WorkoutSession;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;

class WorkoutLogger
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
    public function log(User $user, array $input): array
    {
        $existing = $this->existingIdempotentSession($user, $input);

        if ($existing !== null) {
            return [
                'idempotent_replay' => true,
                'possible_duplicate' => null,
                'unresolved_or_ambiguous_items' => [],
                'saved_session' => $this->summaries->workout($existing),
                'normalized_summary' => $this->normalizedSummary($existing),
            ];
        }

        $validation = $this->exerciseWriter->validateExercises($user, $input['exercises'] ?? []);

        if ($validation !== []) {
            return [
                'refused' => true,
                'refusal_reason' => 'Workout entries are structurally invalid: each entry needs a raw_phrase or a known exercise_id.',
                'unresolved_or_ambiguous_items' => $validation,
                'saved_session' => null,
                'normalized_summary' => null,
            ];
        }

        $possibleDuplicate = $this->possibleDuplicate($user, $input);
        $outcomes = [];

        $session = DB::transaction(function () use ($user, $input, &$outcomes): WorkoutSession {
            $outcomes = [];
            $startedAt = $this->occurredAt($input);
            $session = WorkoutSession::query()->create([
                'user_id' => $user->id,
                'name' => $input['name'] ?? 'Logged workout',
                'started_at' => $startedAt,
                'completed_at' => $input['completed_at'] ?? $startedAt,
                'occurred_timezone' => $input['timezone'] ?? $user->profile?->timezone ?? 'UTC',
                'status' => 'completed',
                'kind' => $input['kind'] ?? 'mixed',
                'source' => 'chatgpt',
                'perceived_effort' => $input['perceived_effort'] ?? null,
                'bodyweight_kg' => $this->unitNormalizer->normalizeBodyweight(
                    isset($input['bodyweight_value']) ? (float) $input['bodyweight_value'] : null,
                    $input['bodyweight_unit'] ?? null,
                ),
                'notes' => $input['notes'] ?? null,
                'raw_input' => $input['raw_input'] ?? null,
                'source_message_id' => $input['source_message_id'] ?? null,
                'idempotency_key' => $input['idempotency_key'] ?? null,
            ]);

            foreach (array_values($input['exercises'] ?? []) as $index => $exerciseInput) {
                $outcomes[] = $this->exerciseWriter->createWorkoutExercise($user, $session, $exerciseInput, $index + 1)['outcome'];
            }

            $session->changeEvents()->create([
                'user_id' => $user->id,
                'event_type' => 'created',
                'reason' => 'Logged through MCP.',
                'metadata' => ['raw_input' => $input['raw_input'] ?? null],
            ]);

            return $session;
        }, attempts: 3);

        $session = $session->fresh(['exercises.sets', 'exercises.exercise']);

        return [
            'refused' => false,
            'idempotent_replay' => false,
            'possible_duplicate' => $possibleDuplicate,
            'unresolved_or_ambiguous_items' => [],
            ...$this->exerciseWriter->outcomeSummary($outcomes),
            'saved_session' => $this->summaries->workout($session),
            'normalized_summary' => $this->normalizedSummary($session),
        ];
    }

    /**
     * @param  array<string, mixed>  $input
     */
    private function existingIdempotentSession(User $user, array $input): ?WorkoutSession
    {
        if (empty($input['idempotency_key']) && empty($input['source_message_id'])) {
            return null;
        }

        return WorkoutSession::query()
            ->where('user_id', $user->id)
            ->where(function (Builder $builder) use ($input): void {
                if (! empty($input['idempotency_key'])) {
                    $builder->orWhere('idempotency_key', $input['idempotency_key']);
                }

                if (! empty($input['source_message_id'])) {
                    $builder->orWhere('source_message_id', $input['source_message_id']);
                }
            })
            ->with(['exercises.sets', 'exercises.exercise'])
            ->first();
    }

    /**
     * @param  array<string, mixed>  $input
     * @return array<string, mixed>|null
     */
    private function possibleDuplicate(User $user, array $input): ?array
    {
        if (empty($input['raw_input'])) {
            return null;
        }

        $existing = WorkoutSession::query()
            ->where('user_id', $user->id)
            ->where('raw_input', $input['raw_input'])
            ->where('started_at', '>=', now()->subDays(3))
            ->first();

        return $existing ? [
            'workout_id' => $existing->id,
            'message' => 'A workout with identical raw input was logged recently.',
        ] : null;
    }

    /**
     * @param  array<string, mixed>  $input
     */
    private function occurredAt(array $input): Carbon
    {
        return isset($input['occurred_at'])
            ? Carbon::parse($input['occurred_at'], $input['timezone'] ?? null)
            : now();
    }

    /**
     * @return array<string, mixed>
     */
    private function normalizedSummary(WorkoutSession $session): array
    {
        $session->loadMissing(['exercises.sets']);

        return [
            'workout_id' => $session->id,
            'exercise_count' => $session->exercises->count(),
            'set_count' => $session->exercises->flatMap->sets->count(),
            'loaded_volume_kg_reps' => round((float) $session->exercises->flatMap->sets->sum(fn ($set): float => (float) ($set->load_kg ?? 0) * (int) ($set->reps ?? 0)), 2),
            'duration_seconds' => (int) $session->exercises->flatMap->sets->sum('duration_seconds'),
            'distance_meters' => round((float) $session->exercises->flatMap->sets->sum('distance_meters'), 2),
        ];
    }
}
