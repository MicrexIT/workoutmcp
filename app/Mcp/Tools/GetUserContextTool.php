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

        return $this->structured([
            'stale_active_session' => $sessions->staleActiveSessionNotice($user),
            'user_context' => [
                'name' => $user->name,
                'email' => $user->email,
                'preferred_weight_unit' => $profile?->preferred_weight_unit ?? 'kg',
                'preferred_distance_unit' => $profile?->preferred_distance_unit ?? 'm',
                'timezone' => $profile?->timezone ?? 'UTC',
                'goals' => $profile?->goals,
                'injuries_constraints' => $profile?->injuries_constraints,
                'available_equipment' => $profile?->available_equipment ?? [],
                'notes' => $profile?->notes,
            ],
        ], 'User context loaded.');
    }

    public function schema(JsonSchema $schema): array
    {
        return [];
    }
}
