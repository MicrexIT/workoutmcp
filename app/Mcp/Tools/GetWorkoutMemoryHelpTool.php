<?php

namespace App\Mcp\Tools;

use App\Mcp\Tools\Concerns\BuildsWorkoutOutputSchemas;
use Illuminate\Contracts\JsonSchema\JsonSchema;
use Laravel\Mcp\Response;
use Laravel\Mcp\ResponseFactory;
use Laravel\Mcp\Server\Attributes\Description;
use Laravel\Mcp\Server\Attributes\Name;
use Laravel\Mcp\Server\Tool;
use Laravel\Mcp\Server\Tools\Annotations\IsDestructive;
use Laravel\Mcp\Server\Tools\Annotations\IsOpenWorld;
use Laravel\Mcp\Server\Tools\Annotations\IsReadOnly;

#[Name('get_workout_memory_help')]
#[Description('Return a concise quickstart for Workout Memory. Use when the user asks for help, asks what they can do, appears new, or wants to add old workouts from notes, text, tables, or CSV-like data.')]
#[IsReadOnly]
#[IsDestructive(false)]
#[IsOpenWorld(false)]
class GetWorkoutMemoryHelpTool extends Tool
{
    use BuildsWorkoutOutputSchemas;

    public function handle(): ResponseFactory
    {
        return Response::structured([
            'ok' => true,
            'message' => 'Workout Memory help loaded.',
            'quickstart' => [
                'summary' => 'Workout Memory stores structured training history from natural language so the user can log, recall, correct, and plan from their workouts.',
                'modes' => [
                    'live_workout' => [
                        'label' => 'Log live exercises while training',
                        'how_to_start' => 'Say what is happening now, such as "starting legs" or "add leg press 3x10 at 120 kg".',
                        'assistant_behavior' => 'Use the live session tools: start the session, append exercises or notes as the user trains, then finish it when they say they are done.',
                    ],
                    'completed_workout' => [
                        'label' => 'Log a completed workout',
                        'how_to_start' => 'Dump the finished session in one message, including date, exercises, sets, durations, distances, notes, or effort if known.',
                        'assistant_behavior' => 'Use log_workout for one completed session and preserve the user\'s raw wording for exercise resolution.',
                    ],
                    'bulk_previous_sessions' => [
                        'label' => 'Add past sessions from notes or CSV',
                        'how_to_start' => 'Paste saved notes, a table, or CSV-like data and ask the assistant to add the past sessions.',
                        'assistant_behavior' => 'Split the pasted data into individual completed workouts and call log_workout once per session. Preserve dates and raw text; ask only when session boundaries or dates are genuinely ambiguous.',
                    ],
                    'profile_setup' => [
                        'label' => 'Set training context',
                        'how_to_start' => 'Tell the assistant durable goals, injuries or constraints, preferred units, timezone, and available equipment.',
                        'assistant_behavior' => 'Use update_user_context for stable preferences, not one-off workout constraints.',
                    ],
                    'corrections' => [
                        'label' => 'Correct mistakes',
                        'how_to_start' => 'Say what was wrong, such as "that was calf press, not leg press" or "remove the duplicate set".',
                        'assistant_behavior' => 'Fetch the affected workout when needed, update it in place, and remember confirmed phrase mappings when useful.',
                    ],
                    'history_and_planning' => [
                        'label' => 'Ask about history or planning',
                        'how_to_start' => 'Ask for recent workouts, exercise history, best efforts, or what to train next.',
                        'assistant_behavior' => 'Read user context and training summaries, then answer conversationally without saving a plan unless the user later asks to log it.',
                    ],
                    'sharing' => [
                        'label' => 'Share a completed workout',
                        'how_to_start' => 'Ask for a share link after a completed workout.',
                        'assistant_behavior' => 'Offer sharing when useful, but create a public link only after the user explicitly says yes.',
                    ],
                ],
                'suggested_first_messages' => [
                    'I am starting a workout now: legs.',
                    'Log yesterday: bench press 5x5 at 80 kg, incline dumbbell press 3x10 at 26 kg.',
                    'Here are my old training notes. Please add each past session to Workout Memory.',
                    'My goals are strength and mobility. I have rings, dumbbells, and a pull-up bar.',
                ],
            ],
            'important_notes' => [
                'Bulk previous-session import is handled by the AI client parsing pasted notes or CSV-like data into normal per-session log_workout calls; there is no separate CSV upload tool.',
                'Corrections should update existing workouts in place instead of logging duplicate corrected workouts.',
            ],
        ]);
    }

    public function schema(JsonSchema $schema): array
    {
        return [];
    }

    public function outputSchema(JsonSchema $schema): array
    {
        return $this->baseOutputSchema($schema, [
            'quickstart' => $schema->object([
                'summary' => $schema->string()->required(),
                'modes' => $schema->object()
                    ->required()
                    ->description('Map of workflow mode keys to label, starting prompt, and assistant behavior.'),
                'suggested_first_messages' => $schema->array()->required()->items($schema->string()),
            ])->required(),
            'important_notes' => $schema->array()->required()->items($schema->string()),
        ]);
    }
}
