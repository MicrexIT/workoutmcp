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

#[Name('finish_workout_session')]
#[Description('Finish the active in-progress workout when the user says they are done. Sets status=completed and can store final RPE, bodyweight, notes, and completed_at. Do not use this to log an older completed workout; use log_workout for that. Provide a stable per-action idempotency_key such as "<message_id>:finish".')]
#[IsIdempotent]
class FinishWorkoutSessionTool extends Tool
{
    use ResolvesWorkoutUser;

    public function handle(Request $request, CurrentUserResolver $users, WorkoutSessionManager $sessions): ResponseFactory
    {
        $validated = $request->validate([
            'occurred_at' => ['sometimes', 'nullable', 'string'],
            'timezone' => ['sometimes', 'nullable', 'string', 'max:80'],
            'name' => ['sometimes', 'nullable', 'string', 'max:160'],
            'kind' => ['sometimes', 'nullable', 'string', 'max:80'],
            'notes' => ['sometimes', 'nullable', 'string'],
            'reason' => ['sometimes', 'nullable', 'string'],
            'raw_input' => ['sometimes', 'nullable', 'string'],
            'perceived_effort' => ['sometimes', 'nullable', 'integer', 'min:1', 'max:10'],
            'bodyweight_value' => ['sometimes', 'nullable', 'numeric'],
            'bodyweight_unit' => ['sometimes', 'nullable', 'in:kg,lb'],
            'idempotency_key' => ['required', 'string', 'max:255'],
            'source_message_id' => ['sometimes', 'nullable', 'string', 'max:255'],
        ]);

        return $this->structured($sessions->finish($this->currentUser($users), $validated), 'Workout session finish handled.');
    }

    public function schema(JsonSchema $schema): array
    {
        return [
            'occurred_at' => $schema->string()->nullable(),
            'timezone' => $schema->string()->nullable(),
            'name' => $schema->string()->nullable(),
            'kind' => $schema->string()->nullable(),
            'notes' => $schema->string()->nullable(),
            'reason' => $schema->string()->nullable(),
            'raw_input' => $schema->string()->nullable(),
            'perceived_effort' => $schema->integer()->nullable(),
            'bodyweight_value' => $schema->number()->nullable(),
            'bodyweight_unit' => $schema->string()->enum(['kg', 'lb'])->nullable(),
            'idempotency_key' => $schema->string()->description('Stable per-finish key. Reuse on retry.')->required(),
            'source_message_id' => $schema->string()->nullable(),
        ];
    }
}
