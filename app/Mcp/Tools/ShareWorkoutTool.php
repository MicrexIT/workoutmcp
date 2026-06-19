<?php

namespace App\Mcp\Tools;

use App\Mcp\Tools\Concerns\ResolvesWorkoutUser;
use App\Services\WorkoutMemory\CurrentUserResolver;
use App\Services\WorkoutMemory\WorkoutShareService;
use Illuminate\Contracts\JsonSchema\JsonSchema;
use Laravel\Mcp\Request;
use Laravel\Mcp\ResponseFactory;
use Laravel\Mcp\Server\Attributes\Description;
use Laravel\Mcp\Server\Attributes\Name;
use Laravel\Mcp\Server\Tool;
use Laravel\Mcp\Server\Tools\Annotations\IsDestructive;
use Laravel\Mcp\Server\Tools\Annotations\IsIdempotent;
use Laravel\Mcp\Server\Tools\Annotations\IsOpenWorld;
use Laravel\Mcp\Server\Tools\Annotations\IsReadOnly;

#[Name('share_workout')]
#[Description('Create a public, revocable share link for a completed workout. Returns share_url plus share_text, a ready-to-paste message for the user. Call it only when the user explicitly asks to share: offer sharing after a workout is logged or finished (especially on a new best), never create links unprompted. Defaults to the most recent completed workout when workout_session_id is omitted. Refuses in-progress sessions; finish_workout_session first. Re-sharing an already-shared workout returns the same active link. Links are revocable from the web dashboard and die when the workout is deleted.')]
#[IsReadOnly(false)]
#[IsDestructive(false)]
#[IsOpenWorld]
#[IsIdempotent]
class ShareWorkoutTool extends Tool
{
    use ResolvesWorkoutUser;

    public function handle(Request $request, CurrentUserResolver $users, WorkoutShareService $shares): ResponseFactory
    {
        $validated = $request->validate([
            'workout_session_id' => ['sometimes', 'nullable', 'integer'],
        ]);

        return $this->structured(
            $shares->share($this->currentUser($users), $validated['workout_session_id'] ?? null),
            'Workout share handled.',
        );
    }

    public function schema(JsonSchema $schema): array
    {
        return [
            'workout_session_id' => $schema->integer()->nullable()->description('The workout to share, from saved_session.id, session.id, or list_recent_workouts. Omit to share the most recent completed workout.'),
        ];
    }

    public function outputSchema(JsonSchema $schema): array
    {
        return $this->baseOutputSchema($schema, [
            'created' => $schema->boolean(),
            'share_url' => $schema->string()->nullable(),
            'share_text' => $schema->string()->nullable(),
            'shared_workout' => $schema->object([
                'id' => $schema->integer()->required(),
                'name' => $schema->string()->required()->nullable(),
            ])->nullable(),
            'note' => $schema->string()->nullable(),
            'confirmation_hint' => $schema->string()->nullable(),
        ]);
    }
}
