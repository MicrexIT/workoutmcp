<?php

namespace App\Mcp\Servers;

use App\Mcp\Prompts\BulkWorkoutImportPrompt;
use App\Mcp\Prompts\TrainingAnalysisPrompt;
use App\Mcp\Prompts\WorkoutMemoryGuidancePrompt;
use App\Mcp\Tools\AppendSessionStoryTool;
use App\Mcp\Tools\AppendWorkoutExerciseTool;
use App\Mcp\Tools\CreateExerciseTool;
use App\Mcp\Tools\DeleteWorkoutTool;
use App\Mcp\Tools\FinishWorkoutSessionTool;
use App\Mcp\Tools\GetCurrentWorkoutSessionTool;
use App\Mcp\Tools\GetExerciseHistoryTool;
use App\Mcp\Tools\GetTrainingSummaryTool;
use App\Mcp\Tools\GetUserContextTool;
use App\Mcp\Tools\GetWorkoutMemoryHelpTool;
use App\Mcp\Tools\GetWorkoutTool;
use App\Mcp\Tools\ListRecentWorkoutsTool;
use App\Mcp\Tools\LogWorkoutTool;
use App\Mcp\Tools\RememberExercisePhraseTool;
use App\Mcp\Tools\ResolveExerciseMentionsTool;
use App\Mcp\Tools\SearchExercisesTool;
use App\Mcp\Tools\ShareWorkoutTool;
use App\Mcp\Tools\StartWorkoutSessionTool;
use App\Mcp\Tools\UpdateUserContextTool;
use App\Mcp\Tools\UpdateWorkoutTool;
use Laravel\Mcp\Server;
use Laravel\Mcp\Server\Attributes\Instructions;
use Laravel\Mcp\Server\Attributes\Name;
use Laravel\Mcp\Server\Attributes\Version;

#[Name('Workout Memory Server')]
#[Version('0.0.1')]
#[Instructions('Workout Memory MCP is a ChatGPT-first workout tracker. When the user asks for help, asks what they can do, appears new, or wants to add old notes or CSV-like data, call get_workout_memory_help; for pasted old notes or CSV-like data, parse it into separate completed sessions and use normal per-session log_workout calls because there is no separate bulk import tool. Resolve exercise mentions before logging when convenient, but logging never depends on it: log_workout, append_workout_exercise, and update_workout add_exercise resolve raw phrases server-side. They prefer existing exercises and buckets when confident; as a last resort they create a clearly-flagged user-scoped exercise and report it in auto_created_exercises — they never refuse or drop entries for resolution reasons. Always send each entry\'s raw_phrase. Review resolution_outcomes and assumed_matches in responses, tell the user about assumptions and auto-created exercises, and correct mappings with remember_exercise_phrase or update_workout. create_exercise requires discovery evidence and refuses likely duplicates. Use start_workout_session, append_session_story, append_workout_exercise, and finish_workout_session for a live in-progress workout the user is doing now. If the user says to add something to the last/just-created session, use get_current_workout_session when needed and append_workout_exercise with target_session=latest_completed when that session is clearly recent. Use log_workout for a separate completed workout from earlier, such as yesterday or a fully described past session. Provide unique per-action idempotency_key values for new mutating session tools; do not reuse one key for multiple appends in the same user message. For planning, call get_user_context and get_training_summary, then propose workouts conversationally without saving a plan unless the user asks later to log a completed workout. share_workout creates a public link for a completed workout: offer it when log_workout or finish_workout_session responses flag share_available and there is something worth sharing, but call it only when the user explicitly says yes; it never applies to a live in-progress session.')]
class WorkoutMemoryServer extends Server
{
    public int $defaultPaginationLength = 50;

    protected array $tools = [
        GetWorkoutMemoryHelpTool::class,
        GetUserContextTool::class,
        UpdateUserContextTool::class,
        GetCurrentWorkoutSessionTool::class,
        StartWorkoutSessionTool::class,
        AppendSessionStoryTool::class,
        AppendWorkoutExerciseTool::class,
        FinishWorkoutSessionTool::class,
        ResolveExerciseMentionsTool::class,
        SearchExercisesTool::class,
        RememberExercisePhraseTool::class,
        CreateExerciseTool::class,
        LogWorkoutTool::class,
        ListRecentWorkoutsTool::class,
        GetWorkoutTool::class,
        GetExerciseHistoryTool::class,
        GetTrainingSummaryTool::class,
        UpdateWorkoutTool::class,
        DeleteWorkoutTool::class,
        ShareWorkoutTool::class,
    ];

    protected array $resources = [
        //
    ];

    protected array $prompts = [
        WorkoutMemoryGuidancePrompt::class,
        BulkWorkoutImportPrompt::class,
        TrainingAnalysisPrompt::class,
    ];
}
