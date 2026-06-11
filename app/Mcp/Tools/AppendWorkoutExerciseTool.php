<?php

namespace App\Mcp\Tools;

use App\Mcp\Tools\Concerns\ResolvesWorkoutUser;
use App\Services\WorkoutMemory\CurrentUserResolver;
use App\Services\WorkoutMemory\WorkoutSessionManager;
use Illuminate\Contracts\JsonSchema\JsonSchema;
use Laravel\Mcp\Request;
use Laravel\Mcp\ResponseFactory;
use Laravel\Mcp\Server\Attributes\Description;
use Laravel\Mcp\Server\Attributes\Name;
use Laravel\Mcp\Server\Tool;
use Laravel\Mcp\Server\Tools\Annotations\IsIdempotent;

#[Name('append_workout_exercise')]
#[Description('Append one finished exercise block with sets to an active session or a recent completed "last session". Always send the exercise raw_phrase; exercise_id and resolution_id are optional hints — never invent ids or use position numbers; omit exercise_id when unsure. The server resolves phrases itself and never drops the entry — as a last resort it creates a clearly-flagged exercise reported in auto_created_exercises. Hints contradicting the raw_phrase are ignored and reported in ignored_exercise_hints. Defaults to target_session=active_or_new for live workout phrases like "log leg press now". Use target_session=latest_completed when the user says "add this to the last session" or the session was just logged/completed. Use log_workout for a separate completed workout from earlier. Provide a stable per-exercise idempotency_key such as "<message_id>:append:leg-press".')]
#[IsIdempotent]
class AppendWorkoutExerciseTool extends Tool
{
    use ResolvesWorkoutUser;

    public function handle(Request $request, CurrentUserResolver $users, WorkoutSessionManager $sessions): ResponseFactory
    {
        $validated = $request->validate([
            'target_session' => ['sometimes', 'nullable', 'in:active_or_new,active,latest_completed'],
            'workout_id' => ['sometimes', 'nullable', 'integer'],
            'name' => ['sometimes', 'nullable', 'string', 'max:160'],
            'occurred_at' => ['sometimes', 'nullable', 'string'],
            'timezone' => ['sometimes', 'nullable', 'string', 'max:80'],
            'kind' => ['sometimes', 'nullable', 'string', 'max:80'],
            'reason' => ['sometimes', 'nullable', 'string'],
            'raw_input' => ['sometimes', 'nullable', 'string'],
            'idempotency_key' => ['required', 'string', 'max:255'],
            'source_message_id' => ['sometimes', 'nullable', 'string', 'max:255'],
            'user_confirmed_recent_target' => ['sometimes', 'boolean'],
            'user_confirmed_stale_active_session' => ['sometimes', 'boolean'],
            'user_confirmed_current_session' => ['sometimes', 'boolean'],
            'exercise' => ['required', 'array'],
            'exercise.exercise_id' => ['sometimes', 'nullable', 'integer'],
            'exercise.resolution_id' => ['sometimes', 'nullable', 'string'],
            'exercise.raw_phrase' => ['nullable', 'string', 'max:255', 'required_without:exercise.exercise_id'],
            'exercise.resolution_type' => ['sometimes', 'nullable', 'string'],
            'exercise.variant_label' => ['sometimes', 'nullable', 'string', 'max:255'],
            'exercise.variant_description' => ['sometimes', 'nullable', 'string'],
            'exercise.prescription' => ['sometimes', 'nullable', 'string'],
            'exercise.notes' => ['sometimes', 'nullable', 'string'],
            'exercise.sets' => ['required', 'array', 'min:1'],
            'exercise.sets.*.set_number' => ['sometimes', 'nullable', 'integer', 'min:1'],
            'exercise.sets.*.reps' => ['sometimes', 'nullable', 'integer', 'min:0'],
            'exercise.sets.*.load_value' => ['sometimes', 'nullable', 'numeric'],
            'exercise.sets.*.load_unit' => ['sometimes', 'nullable', 'in:kg,lb'],
            'exercise.sets.*.load_type' => ['sometimes', 'nullable', 'in:implement,external,assistance,bodyweight,unknown'],
            'exercise.sets.*.duration_seconds' => ['sometimes', 'nullable', 'integer', 'min:0'],
            'exercise.sets.*.distance_value' => ['sometimes', 'nullable', 'numeric'],
            'exercise.sets.*.distance_unit' => ['sometimes', 'nullable', 'in:m,km,mi'],
            'exercise.sets.*.rpe' => ['sometimes', 'nullable', 'numeric', 'min:0', 'max:10'],
            'exercise.sets.*.rir' => ['sometimes', 'nullable', 'numeric', 'min:0', 'max:10'],
            'exercise.sets.*.side' => ['sometimes', 'nullable', 'in:left,right,both,alternating'],
            'exercise.sets.*.success' => ['sometimes', 'nullable', 'boolean'],
            'exercise.sets.*.quality_rating' => ['sometimes', 'nullable', 'integer', 'min:1', 'max:5'],
            'exercise.sets.*.is_warmup' => ['sometimes', 'boolean'],
            'exercise.sets.*.custom_metrics' => ['sometimes', 'nullable', 'array'],
            'exercise.sets.*.raw_set_text' => ['sometimes', 'nullable', 'string'],
            'exercise.sets.*.notes' => ['sometimes', 'nullable', 'string'],
        ]);

        return $this->structured($sessions->appendExercise($this->currentUser($users), $validated), 'Workout exercise append handled.');
    }

    public function schema(JsonSchema $schema): array
    {
        return [
            'target_session' => $schema->string()->enum(['active_or_new', 'active', 'latest_completed'])->default('active_or_new'),
            'workout_id' => $schema->integer()->nullable()->description('Explicit target. Overrides target_session when present.'),
            'name' => $schema->string()->nullable()->description('Used only if active_or_new auto-starts a session.'),
            'occurred_at' => $schema->string()->nullable(),
            'timezone' => $schema->string()->nullable(),
            'kind' => $schema->string()->nullable()->description('Used only if active_or_new auto-starts a session.'),
            'reason' => $schema->string()->nullable(),
            'raw_input' => $schema->string()->nullable(),
            'idempotency_key' => $schema->string()->description('Stable per-exercise append key. Reuse on retry; never reuse for another exercise or story event.')->required(),
            'source_message_id' => $schema->string()->nullable(),
            'user_confirmed_recent_target' => $schema->boolean()->default(false),
            'user_confirmed_stale_active_session' => $schema->boolean()->default(false),
            'user_confirmed_current_session' => $schema->boolean()->default(false),
            'exercise' => $schema->object([
                'raw_phrase' => $schema->string()->description('The user\'s wording for the exercise. Always send it so the server can resolve or correct catalog matches.')->required(),
                'exercise_id' => $schema->integer()->nullable()->description('Optional hint: a real catalog id previously returned by this server (search_exercises, resolve_exercise_mentions, or workout history). Never an invented number or a position index. Omit when unsure — the server resolves raw_phrase itself, and ids that contradict the raw_phrase are ignored.'),
                'resolution_id' => $schema->string()->nullable()->description('Optional evidence id from resolve_exercise_mentions or search_exercises.'),
                'resolution_type' => $schema->string()->nullable()->description('Ignored; the server derives it. Kept for backward compatibility.'),
                'variant_label' => $schema->string()->nullable(),
                'variant_description' => $schema->string()->nullable(),
                'prescription' => $schema->string()->nullable(),
                'notes' => $schema->string()->nullable(),
                'sets' => $schema->array()->items($schema->object([
                    'set_number' => $schema->integer()->nullable(),
                    'reps' => $schema->integer()->nullable(),
                    'load_value' => $schema->number()->nullable(),
                    'load_unit' => $schema->string()->enum(['kg', 'lb'])->nullable(),
                    'load_type' => $schema->string()->enum(['implement', 'external', 'assistance', 'bodyweight', 'unknown'])->nullable(),
                    'duration_seconds' => $schema->integer()->nullable(),
                    'distance_value' => $schema->number()->nullable(),
                    'distance_unit' => $schema->string()->enum(['m', 'km', 'mi'])->nullable(),
                    'rpe' => $schema->number()->nullable(),
                    'rir' => $schema->number()->nullable(),
                    'side' => $schema->string()->enum(['left', 'right', 'both', 'alternating'])->nullable(),
                    'success' => $schema->boolean()->nullable(),
                    'quality_rating' => $schema->integer()->nullable(),
                    'is_warmup' => $schema->boolean()->default(false),
                    'custom_metrics' => $schema->object([])->nullable(),
                    'raw_set_text' => $schema->string()->nullable(),
                    'notes' => $schema->string()->nullable(),
                ]))->required(),
            ])->required(),
        ];
    }
}
