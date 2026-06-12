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

#[Name('start_workout_session')]
#[Description('Start or resume a live in-progress workout session for phrases like "starting legs" or "I am doing leg press now". Do not use for a completed past workout; use log_workout for that. A recent active session is resumed instead of duplicated; an active session inactive for over 18 hours is auto-completed first and reported in auto_finished_stale_session. Provide a stable per-action idempotency_key such as "<message_id>:start".')]
#[IsIdempotent]
class StartWorkoutSessionTool extends Tool
{
    use ResolvesWorkoutUser;

    public function handle(Request $request, CurrentUserResolver $users, WorkoutSessionManager $sessions): ResponseFactory
    {
        $validated = $request->validate([
            'name' => ['sometimes', 'nullable', 'string', 'max:160'],
            'occurred_at' => ['sometimes', 'nullable', 'string'],
            'timezone' => ['sometimes', 'nullable', 'string', 'max:80'],
            'kind' => ['sometimes', 'nullable', 'string', 'max:80'],
            'notes' => ['sometimes', 'nullable', 'string'],
            'raw_input' => ['sometimes', 'nullable', 'string'],
            'idempotency_key' => ['required', 'string', 'max:255'],
            'source_message_id' => ['sometimes', 'nullable', 'string', 'max:255'],
        ]);

        return $this->structured($sessions->start($this->currentUser($users), $validated), 'Workout session start handled.');
    }

    public function schema(JsonSchema $schema): array
    {
        return [
            'name' => $schema->string()->nullable(),
            'occurred_at' => $schema->string()->nullable(),
            'timezone' => $schema->string()->nullable(),
            'kind' => $schema->string()->nullable(),
            'notes' => $schema->string()->nullable(),
            'raw_input' => $schema->string()->nullable(),
            'idempotency_key' => $schema->string()->description('Stable per-action key. Reuse on retry; do not reuse for a later append in the same message.')->required(),
            'source_message_id' => $schema->string()->nullable(),
        ];
    }
}
