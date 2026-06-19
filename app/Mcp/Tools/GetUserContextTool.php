<?php

namespace App\Mcp\Tools;

use App\Mcp\Tools\Concerns\ResolvesWorkoutUser;
use App\Services\WorkoutMemory\CurrentUserResolver;
use App\Services\WorkoutMemory\WorkoutSessionManager;
use Illuminate\Contracts\JsonSchema\JsonSchema;
use Laravel\Mcp\ResponseFactory;
use Laravel\Mcp\Server\Attributes\Description;
use Laravel\Mcp\Server\Attributes\Name;
use Laravel\Mcp\Server\Tool;
use Laravel\Mcp\Server\Tools\Annotations\IsDestructive;
use Laravel\Mcp\Server\Tools\Annotations\IsOpenWorld;
use Laravel\Mcp\Server\Tools\Annotations\IsReadOnly;

#[Name('get_user_context')]
#[Description('Return stable user profile, units, goals, constraints, equipment, and notes needed for workout planning and logging.')]
#[IsReadOnly]
#[IsDestructive(false)]
#[IsOpenWorld(false)]
class GetUserContextTool extends Tool
{
    use ResolvesWorkoutUser;

    public function handle(CurrentUserResolver $users, WorkoutSessionManager $sessions): ResponseFactory
    {
        $user = $this->currentUser($users);
        $profile = $user->profile;
        $availableEquipment = $profile?->available_equipment ?? [];
        $isNewUser = ! $user->workoutSessions()
            ->where('status', 'completed')
            ->exists();
        $profileNeedsSetup = blank($profile?->goals) || $availableEquipment === [];

        return $this->structured([
            'stale_active_session' => $sessions->staleActiveSessionNotice($user),
            'onboarding' => [
                'is_new_user' => $isNewUser,
                'profile_needs_setup' => $profileNeedsSetup,
                'help_tool' => 'get_workout_memory_help',
                'suggested_next_actions' => $this->suggestedNextActions($isNewUser, $profileNeedsSetup),
            ],
            'user_context' => [
                'name' => $user->name,
                'email' => $user->email,
                'preferred_weight_unit' => $profile?->preferred_weight_unit ?? 'kg',
                'preferred_distance_unit' => $profile?->preferred_distance_unit ?? 'm',
                'timezone' => $profile?->timezone ?? 'UTC',
                'goals' => $profile?->goals,
                'injuries_constraints' => $profile?->injuries_constraints,
                'available_equipment' => $availableEquipment,
                'notes' => $profile?->notes,
            ],
        ], 'User context loaded.');
    }

    public function schema(JsonSchema $schema): array
    {
        return [];
    }

    public function outputSchema(JsonSchema $schema): array
    {
        return $this->baseOutputSchema($schema, $this->userContextProperties($schema));
    }

    /**
     * @return array<int, string>
     */
    private function suggestedNextActions(bool $isNewUser, bool $profileNeedsSetup): array
    {
        $actions = $isNewUser
            ? [
                'Start a live workout by saying what you are training now.',
                'Log a completed workout by describing the whole session in one message.',
                'Paste previous notes or CSV-like data and ask the assistant to add past sessions.',
            ]
            : [
                'Ask for recent workout history or a training summary before planning.',
                'Correct any mistaken workout in place instead of logging a duplicate.',
                'Paste previous notes or CSV-like data and ask the assistant to add past sessions.',
            ];

        if ($profileNeedsSetup) {
            $actions[] = 'Share durable goals, constraints, units, timezone, and available equipment.';
        }

        return $actions;
    }
}
