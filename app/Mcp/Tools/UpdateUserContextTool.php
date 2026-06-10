<?php

namespace App\Mcp\Tools;

use App\Mcp\Tools\Concerns\ResolvesWorkoutUser;
use App\Services\WorkoutMemory\CurrentUserResolver;
use Illuminate\Contracts\JsonSchema\JsonSchema;
use Laravel\Mcp\Request;
use Laravel\Mcp\ResponseFactory;
use Laravel\Mcp\Server\Attributes\Description;
use Laravel\Mcp\Server\Attributes\Name;
use Laravel\Mcp\Server\Tool;
use Laravel\Mcp\Server\Tools\Annotations\IsIdempotent;

#[Name('update_user_context')]
#[Description('Update durable user training preferences, goals, constraints, available equipment, or notes. Do not use for one-off workout constraints.')]
#[IsIdempotent]
class UpdateUserContextTool extends Tool
{
    use ResolvesWorkoutUser;

    public function handle(Request $request, CurrentUserResolver $users): ResponseFactory
    {
        $validated = $request->validate([
            'preferred_weight_unit' => ['sometimes', 'in:kg,lb'],
            'preferred_distance_unit' => ['sometimes', 'in:m,km,mi'],
            'timezone' => ['sometimes', 'string', 'max:80'],
            'goals' => ['sometimes', 'nullable', 'string'],
            'injuries_constraints' => ['sometimes', 'nullable', 'string'],
            'available_equipment' => ['sometimes', 'array'],
            'available_equipment.*' => ['string', 'max:80'],
            'notes' => ['sometimes', 'nullable', 'string'],
        ]);

        $user = $this->currentUser($users);
        $profile = $user->profile()->firstOrCreate(['user_id' => $user->id]);
        $profile->fill($validated)->save();

        return app(GetUserContextTool::class)->handle($users);
    }

    public function schema(JsonSchema $schema): array
    {
        return [
            'preferred_weight_unit' => $schema->string()->enum(['kg', 'lb'])->description('Preferred body/load unit.'),
            'preferred_distance_unit' => $schema->string()->enum(['m', 'km', 'mi'])->description('Preferred distance unit.'),
            'timezone' => $schema->string()->description('IANA timezone.'),
            'goals' => $schema->string()->nullable()->description('Durable training goals.'),
            'injuries_constraints' => $schema->string()->nullable()->description('Durable constraints or injuries.'),
            'available_equipment' => $schema->array()->items($schema->string())->description('Stable equipment usually available.'),
            'notes' => $schema->string()->nullable()->description('Other stable planning notes.'),
        ];
    }
}
