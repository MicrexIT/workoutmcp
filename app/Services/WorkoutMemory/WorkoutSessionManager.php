<?php

namespace App\Services\WorkoutMemory;

use App\Models\User;
use App\Models\WorkoutChangeEvent;
use App\Models\WorkoutSession;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;

class WorkoutSessionManager
{
    private const RECENT_COMPLETED_TARGET_HOURS = 36;

    private const ACTIVE_SESSION_STALE_HOURS = 18;

    public function __construct(
        private readonly UnitNormalizer $unitNormalizer,
        private readonly TrainingSummaryService $summaries,
        private readonly WorkoutExerciseWriter $exerciseWriter,
    ) {}

    /**
     * @return array<string, mixed>
     */
    public function current(User $user): array
    {
        $active = $this->activeSession($user);
        $latestCompleted = $this->latestCompletedSession($user);

        return [
            'active_session' => $active ? $this->summaries->workout($active) : null,
            'active_session_is_stale' => $active ? $this->isStaleActiveSession($active) : false,
            'latest_completed_session' => $latestCompleted ? $this->summaries->workout($latestCompleted) : null,
            'append_target_guidance' => [
                'current_live_workout' => 'Use append_session_story or append_workout_exercise with target_session=active_or_new.',
                'just_logged_or_last_session' => 'Use append_workout_exercise with target_session=latest_completed when the user clearly refers to the most recent completed session.',
                'past_completed_workout' => 'Use log_workout for a completed workout from earlier instead of creating an active session.',
                'stale_or_wrongly_completed' => 'An in-progress session inactive for over 18 hours auto-completes on the next write. To continue a workout that was wrongly completed, use update_workout with a reopen_session operation.',
            ],
        ];
    }

    /**
     * @param  array<string, mixed>  $input
     * @return array<string, mixed>
     */
    public function start(User $user, array $input): array
    {
        $existing = $this->idempotentSession($user, $input);

        if ($existing !== null) {
            return [
                'refused' => false,
                'idempotent_replay' => true,
                'session_was_created' => false,
                'active_session' => $this->summaries->workout($existing),
            ];
        }

        $active = $this->activeSession($user);
        $autoFinished = null;

        if ($active !== null && $this->isStaleActiveSession($active)) {
            $autoFinished = $this->autoFinishStaleSession($user, $active);
            $active = null;
        }

        if ($active !== null) {
            return [
                'refused' => false,
                'idempotent_replay' => false,
                'session_was_created' => false,
                'session_was_resumed' => true,
                'active_session' => $this->summaries->workout($active),
            ];
        }

        $session = DB::transaction(function () use ($user, $input): WorkoutSession {
            $startedAt = $this->occurredAt($input);
            $session = WorkoutSession::query()->create([
                'user_id' => $user->id,
                'name' => $input['name'] ?? 'Workout in progress',
                'started_at' => $startedAt,
                'completed_at' => null,
                'occurred_timezone' => $input['timezone'] ?? $user->profile?->timezone ?? 'UTC',
                'status' => 'in_progress',
                'kind' => $input['kind'] ?? 'mixed',
                'source' => 'chatgpt',
                'notes' => $input['notes'] ?? null,
                'raw_input' => $input['raw_input'] ?? null,
                'source_message_id' => $input['source_message_id'] ?? null,
                'idempotency_key' => $input['idempotency_key'] ?? null,
            ]);

            $this->recordEvent($user, $session, 'started', $input, [
                'raw_input' => $input['raw_input'] ?? null,
            ], $startedAt);

            return $session;
        }, attempts: 3);

        return [
            'refused' => false,
            'idempotent_replay' => false,
            'session_was_created' => true,
            'session_was_resumed' => false,
            'auto_finished_stale_session' => $autoFinished,
            'active_session' => $this->summaries->workout($session->fresh(['exercises.sets', 'exercises.exercise', 'changeEvents'])),
        ];
    }

    /**
     * @param  array<string, mixed>  $input
     * @return array<string, mixed>
     */
    public function appendStory(User $user, array $input): array
    {
        $existingEvent = $this->idempotentEvent($user, $input);

        if ($existingEvent !== null) {
            return $this->eventReplayResponse($existingEvent, 'story_event');
        }

        return DB::transaction(function () use ($user, $input): array {
            $existingEvent = $this->idempotentEvent($user, $input);

            if ($existingEvent !== null) {
                return $this->eventReplayResponse($existingEvent, 'story_event');
            }

            $target = $this->targetSession($user, $input, allowAutoStart: true, lock: true);

            if (($target['refused'] ?? false) === true) {
                return $target;
            }

            /** @var WorkoutSession $session */
            $session = $target['session'];
            $event = $this->recordEvent($user, $session, 'story', $input, [
                'story' => $input['story'],
                'raw_input' => $input['raw_input'] ?? null,
            ], $this->occurredAt($input));

            return [
                'refused' => false,
                'idempotent_replay' => false,
                'target_resolution' => $target['target_resolution'],
                'auto_finished_stale_session' => $target['auto_finished_stale_session'] ?? null,
                'story_event' => $this->eventSummary($event),
                'session' => $this->summaries->workout($session->fresh(['exercises.sets', 'exercises.exercise', 'changeEvents'])),
            ];
        }, attempts: 3);
    }

    /**
     * @param  array<string, mixed>  $input
     * @return array<string, mixed>
     */
    public function appendExercise(User $user, array $input): array
    {
        $exerciseInput = $input['exercise'] ?? [];
        $validation = $this->exerciseWriter->validateExercise($user, $exerciseInput);

        if ($validation !== []) {
            return $this->refused(
                'Workout exercise entry is structurally invalid: it needs a raw_phrase or a known exercise_id.',
                ['unresolved_or_ambiguous_items' => $validation],
            );
        }

        $existingEvent = $this->idempotentEvent($user, $input);

        if ($existingEvent !== null) {
            return $this->eventReplayResponse($existingEvent, 'append_event');
        }

        return DB::transaction(function () use ($user, $input, $exerciseInput): array {
            $existingEvent = $this->idempotentEvent($user, $input);

            if ($existingEvent !== null) {
                return $this->eventReplayResponse($existingEvent, 'append_event');
            }

            $target = $this->targetSession($user, $input, allowAutoStart: true, lock: true);

            if (($target['refused'] ?? false) === true) {
                return $target;
            }

            /** @var WorkoutSession $session */
            $session = $target['session'];
            ['workout_exercise' => $workoutExercise, 'outcome' => $outcome] = $this->exerciseWriter->createWorkoutExercise($user, $session, $exerciseInput);
            $event = $this->recordEvent($user, $session, 'exercise_appended', $input, [
                'workout_exercise_id' => $workoutExercise->id,
                'exercise_id' => $workoutExercise->exercise_id,
                'name_snapshot' => $workoutExercise->name_snapshot,
                'raw_input' => $input['raw_input'] ?? null,
            ], $this->occurredAt($input));

            return [
                'refused' => false,
                'idempotent_replay' => false,
                'target_resolution' => $target['target_resolution'],
                'auto_finished_stale_session' => $target['auto_finished_stale_session'] ?? null,
                'append_event' => $this->eventSummary($event),
                'appended_exercise_id' => $workoutExercise->id,
                ...$this->exerciseWriter->outcomeSummary([$outcome]),
                'session' => $this->summaries->workout($session->fresh(['exercises.sets', 'exercises.exercise', 'changeEvents'])),
            ];
        }, attempts: 3);
    }

    /**
     * @param  array<string, mixed>  $input
     * @return array<string, mixed>
     */
    public function finish(User $user, array $input): array
    {
        $existingEvent = $this->idempotentEvent($user, $input);

        if ($existingEvent !== null) {
            return $this->eventReplayResponse($existingEvent, 'finish_event');
        }

        return DB::transaction(function () use ($user, $input): array {
            $existingEvent = $this->idempotentEvent($user, $input);

            if ($existingEvent !== null) {
                return $this->eventReplayResponse($existingEvent, 'finish_event');
            }

            $target = $this->targetSession($user, [...$input, 'target_session' => 'active'], allowAutoStart: false, lock: true, allowStaleActive: true);

            if (($target['refused'] ?? false) === true) {
                $latestCompleted = $this->latestCompletedSession($user);

                return $this->refused('No active workout session to finish.', [
                    'latest_completed_session' => $latestCompleted ? $this->summaries->workout($latestCompleted) : null,
                    'confirmation_hint' => 'The most recent session is already completed. Use append_workout_exercise with target_session=latest_completed or update_workout to amend it, or update_workout with a reopen_session operation to continue it.',
                ]);
            }

            /** @var WorkoutSession $session */
            $session = $target['session'];
            $completedAt = $this->occurredAt($input);
            $updates = [
                'status' => 'completed',
                'completed_at' => $completedAt,
            ];

            foreach (['name', 'kind', 'notes', 'perceived_effort'] as $field) {
                if (array_key_exists($field, $input)) {
                    $updates[$field] = $input[$field];
                }
            }

            if (array_key_exists('bodyweight_value', $input)) {
                $updates['bodyweight_kg'] = $this->unitNormalizer->normalizeBodyweight(
                    $input['bodyweight_value'] === null ? null : (float) $input['bodyweight_value'],
                    $input['bodyweight_unit'] ?? null,
                );
            }

            $session->update($updates);
            $event = $this->recordEvent($user, $session, 'finished', $input, [
                'raw_input' => $input['raw_input'] ?? null,
                'perceived_effort' => $input['perceived_effort'] ?? null,
            ], $completedAt);

            return [
                'refused' => false,
                'idempotent_replay' => false,
                'target_resolution' => $target['target_resolution'],
                'finish_event' => $this->eventSummary($event),
                'session' => $this->summaries->workout($session->fresh(['exercises.sets', 'exercises.exercise', 'changeEvents'])),
            ];
        }, attempts: 3);
    }

    public function activeSession(User $user, bool $lock = false): ?WorkoutSession
    {
        $query = WorkoutSession::query()
            ->where('user_id', $user->id)
            ->where('status', 'in_progress')
            ->latest('started_at');

        if ($lock) {
            $query->lockForUpdate();
        }

        return $query->first();
    }

    private function latestCompletedSession(User $user, bool $lock = false): ?WorkoutSession
    {
        $query = WorkoutSession::query()
            ->where('user_id', $user->id)
            ->where('status', 'completed')
            ->latest('started_at');

        if ($lock) {
            $query->lockForUpdate();
        }

        return $query->first();
    }

    /**
     * Resolve which session an append/finish targets. A stale active session is
     * auto-completed here (the next write self-heals the state) unless the caller
     * is the finish flow, which must be able to close a stale session itself.
     *
     * @param  array<string, mixed>  $input
     * @return array<string, mixed>
     */
    private function targetSession(User $user, array $input, bool $allowAutoStart, bool $lock, bool $allowStaleActive = false): array
    {
        if (isset($input['workout_id'])) {
            $session = WorkoutSession::query()
                ->where('user_id', $user->id)
                ->whereKey((int) $input['workout_id'])
                ->when($lock, fn ($query) => $query->lockForUpdate())
                ->first();

            if ($session === null) {
                return $this->refused('Workout not found.');
            }

            if ($session->status === 'deleted') {
                return $this->refused('Cannot append to a deleted workout.');
            }

            return ['session' => $session, 'target_resolution' => 'specified'];
        }

        $target = (string) ($input['target_session'] ?? 'active_or_new');

        if ($target === 'latest_completed') {
            $session = $this->latestCompletedSession($user, $lock);

            if ($session === null) {
                return $this->refused('No completed workout exists to target.');
            }

            if ($this->isOldCompletedTarget($session) && ! (bool) ($input['user_confirmed_recent_target'] ?? false)) {
                return $this->refused('The latest completed workout is not recent enough to update without confirmation.', [
                    'needs_confirmation' => true,
                    'confirmation_hint' => 'Ask the user whether they mean to update this older completed workout, or use log_workout for a separate past workout.',
                    'latest_completed_session' => $this->summaries->workout($session),
                ]);
            }

            return ['session' => $session, 'target_resolution' => 'latest_completed'];
        }

        $active = $this->activeSession($user, $lock);
        $autoFinished = null;

        if ($active !== null && $this->isStaleActiveSession($active) && ! $allowStaleActive) {
            $autoFinished = $this->autoFinishStaleSession($user, $active);
            $active = null;
        }

        if ($active !== null) {
            return ['session' => $active, 'target_resolution' => 'active'];
        }

        if ($target === 'active') {
            return $this->refused('No active workout session exists. Use start_workout_session first or append with target_session=active_or_new for a current live workout.', [
                'auto_finished_stale_session' => $autoFinished,
            ]);
        }

        if (! $allowAutoStart) {
            return $this->refused('No active workout session exists.');
        }

        $occurredAt = $this->occurredAt($input);

        if ($occurredAt->lt(now()->subHours(self::ACTIVE_SESSION_STALE_HOURS)) && ! (bool) ($input['user_confirmed_current_session'] ?? false)) {
            return $this->refused('This looks like a past completed workout, not a live session append.', [
                'suggested_next_tool' => 'log_workout',
                'confirmation_hint' => 'Use append only for a live session or a just-created recent session. Use log_workout for a completed workout from earlier.',
            ]);
        }

        $created = WorkoutSession::query()->create([
            'user_id' => $user->id,
            'name' => $input['name'] ?? 'Workout in progress',
            'started_at' => $occurredAt,
            'completed_at' => null,
            'occurred_timezone' => $input['timezone'] ?? $user->profile?->timezone ?? 'UTC',
            'status' => 'in_progress',
            'kind' => $input['kind'] ?? 'mixed',
            'source' => 'chatgpt',
            'raw_input' => $input['raw_input'] ?? null,
        ]);

        $startEventInput = $input;
        unset($startEventInput['idempotency_key']);

        $this->recordEvent($user, $created, 'started', $startEventInput, [
            'auto_started' => true,
            'raw_input' => $input['raw_input'] ?? null,
        ], $occurredAt);

        return [
            'session' => $created,
            'target_resolution' => 'auto_started_active',
            'auto_finished_stale_session' => $autoFinished,
        ];
    }

    /**
     * @param  array<string, mixed>  $input
     */
    private function idempotentSession(User $user, array $input): ?WorkoutSession
    {
        if (empty($input['idempotency_key']) && empty($input['source_message_id'])) {
            return null;
        }

        return WorkoutSession::query()
            ->where('user_id', $user->id)
            ->where(function ($query) use ($input): void {
                if (! empty($input['idempotency_key'])) {
                    $query->orWhere('idempotency_key', $input['idempotency_key']);
                }

                if (! empty($input['source_message_id'])) {
                    $query->orWhere('source_message_id', $input['source_message_id']);
                }
            })
            ->first();
    }

    /**
     * @param  array<string, mixed>  $input
     */
    private function idempotentEvent(User $user, array $input): ?WorkoutChangeEvent
    {
        if (empty($input['idempotency_key'])) {
            return null;
        }

        return WorkoutChangeEvent::query()
            ->where('user_id', $user->id)
            ->where('idempotency_key', $input['idempotency_key'])
            ->with('session.exercises.sets', 'session.exercises.exercise', 'session.changeEvents')
            ->first();
    }

    /**
     * @param  array<string, mixed>  $input
     * @param  array<string, mixed>  $metadata
     */
    private function recordEvent(User $user, WorkoutSession $session, string $eventType, array $input, array $metadata, Carbon $occurredAt): WorkoutChangeEvent
    {
        return $session->changeEvents()->create([
            'user_id' => $user->id,
            'event_type' => $eventType,
            'reason' => $input['reason'] ?? null,
            'metadata' => $metadata,
            'idempotency_key' => $input['idempotency_key'] ?? null,
            'source_message_id' => $input['source_message_id'] ?? null,
            'occurred_at' => $occurredAt,
        ]);
    }

    /**
     * @return array<string, mixed>
     */
    private function eventReplayResponse(WorkoutChangeEvent $event, string $eventKey): array
    {
        return [
            'refused' => false,
            'idempotent_replay' => true,
            $eventKey => $this->eventSummary($event),
            'session' => $this->summaries->workout($event->session),
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function eventSummary(WorkoutChangeEvent $event): array
    {
        return [
            'id' => $event->id,
            'event_type' => $event->event_type,
            'reason' => $event->reason,
            'occurred_at' => $event->occurred_at?->toISOString(),
            'metadata' => $event->metadata,
        ];
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
     * Staleness is measured from the last recorded activity, not the start time,
     * so a long live session that is still receiving appends never goes stale,
     * and a reopened session gets a fresh window from its reopen event.
     */
    public function isStaleActiveSession(WorkoutSession $session): bool
    {
        $lastActivity = $this->lastActivityAt($session);

        return $lastActivity !== null && $lastActivity->lt(now()->subHours(self::ACTIVE_SESSION_STALE_HOURS));
    }

    /**
     * Complete an abandoned in-progress session as of its last activity and
     * record an auditable event. Returns the completed session summary so
     * callers can surface what happened to the model.
     *
     * @return array<string, mixed>
     */
    public function autoFinishStaleSession(User $user, WorkoutSession $session): array
    {
        $completedAt = $this->lastActivityAt($session) ?? now();

        $session->update([
            'status' => 'completed',
            'completed_at' => $completedAt,
        ]);

        $this->recordEvent($user, $session, 'auto_finished', [], [
            'reason' => 'In-progress session was inactive past the stale window and was auto-completed.',
        ], $completedAt);

        return $this->summaries->workout($session->fresh(['exercises.sets', 'exercises.exercise', 'changeEvents']));
    }

    /**
     * Conversation-opening recap for a stale in-progress session, so the model
     * can tell the user about it and ask what to do instead of the session
     * silently auto-completing on the next write. Null when nothing is stale.
     *
     * @return array<string, mixed>|null
     */
    public function staleActiveSessionNotice(User $user): ?array
    {
        $active = $this->activeSession($user);

        if ($active === null || ! $this->isStaleActiveSession($active)) {
            return null;
        }

        $lastActivity = $this->lastActivityAt($active);

        return [
            'session' => $this->summaries->workout($active),
            'hours_since_last_activity' => $lastActivity === null ? null : (int) $lastActivity->diffInHours(now()),
            'prompt_user' => 'A workout session is still open from earlier. Briefly recap it (name, when, exercises) and ask the user: mark it completed now (finish_workout_session), keep training it, or leave it — it will auto-complete as of its last activity on the next write.',
        ];
    }

    private function lastActivityAt(WorkoutSession $session): ?Carbon
    {
        $latestEvent = $session->changeEvents()->max('occurred_at');

        return collect([$session->started_at, $latestEvent === null ? null : Carbon::parse($latestEvent)])
            ->filter()
            ->max();
    }

    private function isOldCompletedTarget(WorkoutSession $session): bool
    {
        return $session->started_at?->lt(now()->subHours(self::RECENT_COMPLETED_TARGET_HOURS)) ?? false;
    }

    /**
     * @return array<string, mixed>
     */
    private function refused(string $reason, array $extra = []): array
    {
        return [
            'refused' => true,
            'refusal_reason' => $reason,
            ...$extra,
        ];
    }
}
