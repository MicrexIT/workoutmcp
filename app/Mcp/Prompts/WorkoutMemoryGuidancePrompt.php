<?php

namespace App\Mcp\Prompts;

use Laravel\Mcp\Request;
use Laravel\Mcp\Response;
use Laravel\Mcp\Server\Attributes\Description;
use Laravel\Mcp\Server\Attributes\Name;
use Laravel\Mcp\Server\Attributes\Title;
use Laravel\Mcp\Server\Prompt;
use Laravel\Mcp\Server\Prompts\Argument;

#[Name('workout_memory_guidance')]
#[Title('Workout Memory Guidance')]
#[Description('Guide a user through the best way to use Workout Memory for logging, corrections, history, planning, and sharing.')]
class WorkoutMemoryGuidancePrompt extends Prompt
{
    /**
     * Handle the prompt request.
     */
    public function handle(Request $request): Response
    {
        $validated = $request->validate([
            'user_goal' => ['nullable', 'string', 'max:1000'],
        ], [
            'user_goal.*' => 'The user_goal argument should be a short description of what the user wants to do with Workout Memory.',
        ]);

        $userGoal = trim((string) ($validated['user_goal'] ?? ''));
        $goalGuidance = $userGoal !== ''
            ? "Tailor the guidance to this user goal: {$userGoal}"
            : 'If the user has not given a specific goal yet, give a short menu of useful starting points and ask which one they want.';

        return Response::text(<<<PROMPT
Use the Workout Memory MCP as the user's workout tracker and coach-facing training log.

First, call get_workout_memory_help when it is available so your guidance matches the server's current capabilities. Then give a concise, practical orientation.

{$goalGuidance}

Cover only what is relevant:
- live workouts: use start_workout_session, append_session_story, append_workout_exercise, and finish_workout_session;
- completed workouts: use log_workout for one finished session;
- old notes or CSV-like data: split into separate completed sessions and use log_workout once per session;
- profile setup: use update_user_context for durable goals, constraints, equipment, units, and timezone;
- corrections: fetch the affected workout when needed, update it in place, and remember confirmed phrase mappings when useful;
- history and planning: read user context and training summaries before giving advice;
- sharing: create a public share link only after the user explicitly asks for one.

Keep the answer short and action-oriented. Do not log, update, delete, or share anything until the user gives concrete data or explicitly asks for an action. Preserve raw exercise wording whenever a workout is logged.
PROMPT);
    }

    /**
     * Get the prompt's arguments.
     *
     * @return array<int, Argument>
     */
    public function arguments(): array
    {
        return [
            new Argument(
                name: 'user_goal',
                description: 'Optional short context about what the user wants to do, such as onboarding, logging a workout, importing old notes, correcting data, or planning.',
            ),
        ];
    }
}
