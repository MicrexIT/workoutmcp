<?php

namespace App\Mcp\Prompts;

use Laravel\Mcp\Request;
use Laravel\Mcp\Response;
use Laravel\Mcp\Server\Attributes\Description;
use Laravel\Mcp\Server\Attributes\Name;
use Laravel\Mcp\Server\Attributes\Title;
use Laravel\Mcp\Server\Prompt;
use Laravel\Mcp\Server\Prompts\Argument;

#[Name('training_analysis_review')]
#[Title('Training Analysis Review')]
#[Description('Analyze a user\'s workout history for trends, weak spots, recovery risks, and practical next-step improvements.')]
class TrainingAnalysisPrompt extends Prompt
{
    /**
     * Handle the prompt request.
     */
    public function handle(Request $request): Response
    {
        $validated = $request->validate([
            'scope' => ['nullable', 'string', 'max:300'],
            'question_or_goal' => ['nullable', 'string', 'max:1000'],
        ], [
            'scope.*' => 'The scope argument should describe the period or subset to analyze, such as last 4 weeks, ring strength, or recent leg sessions.',
            'question_or_goal.*' => 'The question_or_goal argument should describe what the user wants to improve or understand.',
        ]);

        $scope = trim((string) ($validated['scope'] ?? ''));
        $questionOrGoal = trim((string) ($validated['question_or_goal'] ?? ''));

        $scopeGuidance = $scope !== ''
            ? "Analyze this scope: {$scope}"
            : 'Use a sensible recent scope first, then broaden only if the data is too sparse.';

        $goalGuidance = $questionOrGoal !== ''
            ? "Prioritize this user question or goal: {$questionOrGoal}"
            : 'If the user has not named a goal, infer likely priorities from their durable context and recent training, and say what you inferred.';

        return Response::text(<<<PROMPT
Analyze the user's Workout Memory history and suggest useful training improvements.

{$scopeGuidance}
{$goalGuidance}

Use the MCP data before advising:
1. Call get_user_context for durable goals, constraints, equipment, units, and preferences.
2. Call get_training_summary for recent volume, frequency, exercise trends, and notable efforts.
3. Use list_recent_workouts, get_workout, or get_exercise_history when you need evidence for a specific claim.

Look for:
- consistency, frequency, and long gaps;
- progression or stalled lifts, skills, durations, or distances;
- weak spots and undertrained movement patterns;
- recovery risks from repeated hard days, sudden jumps, or missing rest;
- imbalance between strength, skill, conditioning, and mobility work;
- data quality gaps that make conclusions uncertain.

Separate observations from recommendations. Tie each major observation to evidence from the tools, and say when the data is too sparse or inconsistent to support a firm conclusion. If a missing goal, injury constraint, or date range would materially change the advice, ask one focused follow-up question before giving detailed programming advice.

Give the user a concise review with evidence-backed observations, clear caveats, and 3 to 6 practical next steps. Prefer small changes they can apply in the next week. Do not diagnose injuries or present medical advice. Do not save a plan or log a workout unless the user explicitly asks.
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
                name: 'scope',
                description: 'Optional period or subset to analyze, such as last 4 weeks, recent leg sessions, ring work, or conditioning.',
            ),
            new Argument(
                name: 'question_or_goal',
                description: 'Optional user question or improvement goal to prioritize during the analysis.',
            ),
        ];
    }
}
