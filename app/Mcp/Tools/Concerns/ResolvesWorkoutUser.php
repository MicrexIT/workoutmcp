<?php

namespace App\Mcp\Tools\Concerns;

use App\Models\User;
use App\Services\WorkoutMemory\CurrentUserResolver;
use Laravel\Mcp\Response;
use Laravel\Mcp\ResponseFactory;

trait ResolvesWorkoutUser
{
    use BuildsWorkoutOutputSchemas;

    protected function currentUser(CurrentUserResolver $resolver): User
    {
        return $resolver->user();
    }

    /**
     * @param  array<string, mixed>  $content
     */
    protected function structured(array $content, string $message = 'OK'): ResponseFactory
    {
        return Response::structured([
            'ok' => ! ($content['refused'] ?? false),
            'message' => $message,
            ...$content,
        ]);
    }
}
