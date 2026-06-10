<?php

namespace App\Services\WorkoutMemory;

use App\Models\User;
use Illuminate\Auth\AuthenticationException;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;

class CurrentUserResolver
{
    /**
     * @throws AuthenticationException
     */
    public function user(): User
    {
        $authenticatedUser = Auth::user();

        if ($authenticatedUser instanceof User) {
            return $this->withProfile($authenticatedUser);
        }

        if (! app()->runningInConsole()) {
            throw new AuthenticationException('No authenticated workout memory user.');
        }

        $email = (string) config('workout_memory.single_user.email');

        $user = User::query()->firstOrCreate(
            ['email' => $email],
            [
                'name' => (string) config('workout_memory.single_user.name'),
                'password' => Hash::make(str()->random(32)),
            ],
        );

        return $this->withProfile($user);
    }

    public function withProfile(User $user): User
    {
        $user->profile()->firstOrCreate(
            ['user_id' => $user->id],
            $this->defaultProfileAttributes(),
        );

        return $user->refresh()->load('profile');
    }

    /**
     * @return array<string, mixed>
     */
    public function defaultProfileAttributes(): array
    {
        return [
            'preferred_weight_unit' => (string) config('workout_memory.single_user.preferred_weight_unit'),
            'preferred_distance_unit' => (string) config('workout_memory.single_user.preferred_distance_unit'),
            'timezone' => (string) config('workout_memory.single_user.timezone'),
            'available_equipment' => ['rings', 'pull-up bar', 'dumbbells', 'bodyweight'],
            'goals' => 'Strength, rings/calisthenics skill, handstand practice, mobility, and sustainable conditioning.',
        ];
    }
}
