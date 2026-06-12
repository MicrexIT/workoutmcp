<?php

namespace Database\Factories;

use App\Models\User;
use App\Models\WorkoutSession;
use App\Models\WorkoutShare;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/**
 * @extends Factory<WorkoutShare>
 */
class WorkoutShareFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'user_id' => User::factory(),
            'workout_session_id' => WorkoutSession::factory(),
            'slug' => strtolower(Str::random(14)),
            'revoked_at' => null,
        ];
    }

    public function revoked(): static
    {
        return $this->state(fn (): array => ['revoked_at' => now()]);
    }
}
