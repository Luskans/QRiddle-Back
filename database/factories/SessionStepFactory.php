<?php

namespace Database\Factories;

use App\Models\GameSession;
use App\Models\Step;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\SessionStep>
 */
class SessionStepFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'game_session_id'  => GameSession::factory(),
            'step_id'          => Step::factory(),
            'hint_used_number' => fake()->numberBetween(0, 5),
            'status'           => fake()->randomElement(['active', 'completed', 'abandoned']),
            'start_time'       => fake()->dateTimeBetween('-1 week', 'now'),
            'end_time'         => fake()->optional()->dateTimeBetween('now', '+1 week'),
        ];
    }
}
