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

#[Name('append_session_story')]
#[Description('Append narrative context to a live or recent workout without creating sets, for notes like "started leg press", "knee felt tight", or "resting". Defaults to active_or_new for current live-session comments. Use target_session=latest_completed only when the user clearly means the just-finished/last completed session. Use log_workout instead for an older completed workout. Provide a unique per-story idempotency_key.')]
#[IsIdempotent]
class AppendSessionStoryTool extends Tool
{
    use ResolvesWorkoutUser;

    public function handle(Request $request, CurrentUserResolver $users, WorkoutSessionManager $sessions): ResponseFactory
    {
        $validated = $request->validate([
            'story' => ['required', 'string'],
            'target_session' => ['sometimes', 'nullable', 'in:active_or_new,active,latest_completed'],
            'workout_id' => ['sometimes', 'nullable', 'integer'],
            'occurred_at' => ['sometimes', 'nullable', 'string'],
            'timezone' => ['sometimes', 'nullable', 'string', 'max:80'],
            'name' => ['sometimes', 'nullable', 'string', 'max:160'],
            'kind' => ['sometimes', 'nullable', 'string', 'max:80'],
            'reason' => ['sometimes', 'nullable', 'string'],
            'raw_input' => ['sometimes', 'nullable', 'string'],
            'idempotency_key' => ['required', 'string', 'max:255'],
            'source_message_id' => ['sometimes', 'nullable', 'string', 'max:255'],
            'user_confirmed_recent_target' => ['sometimes', 'boolean'],
            'user_confirmed_stale_active_session' => ['sometimes', 'boolean'],
            'user_confirmed_current_session' => ['sometimes', 'boolean'],
        ]);

        return $this->structured($sessions->appendStory($this->currentUser($users), $validated), 'Workout session story append handled.');
    }

    public function schema(JsonSchema $schema): array
    {
        return [
            'story' => $schema->string()->description('Short narrative note to attach to the session.')->required(),
            'target_session' => $schema->string()->enum(['active_or_new', 'active', 'latest_completed'])->default('active_or_new'),
            'workout_id' => $schema->integer()->nullable(),
            'occurred_at' => $schema->string()->nullable(),
            'timezone' => $schema->string()->nullable(),
            'name' => $schema->string()->nullable()->description('Used only if active_or_new auto-starts a session.'),
            'kind' => $schema->string()->nullable()->description('Used only if active_or_new auto-starts a session.'),
            'reason' => $schema->string()->nullable(),
            'raw_input' => $schema->string()->nullable(),
            'idempotency_key' => $schema->string()->description('Stable per-story key. Reuse on retry; do not reuse for an exercise append.')->required(),
            'source_message_id' => $schema->string()->nullable(),
            'user_confirmed_recent_target' => $schema->boolean()->default(false),
            'user_confirmed_stale_active_session' => $schema->boolean()->default(false),
            'user_confirmed_current_session' => $schema->boolean()->default(false),
        ];
    }
}
