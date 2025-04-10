<?php

namespace Database\Factories;

use App\Models\Step;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\Hint>
 */
class HintFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'step_id'      => Step::factory(),
            'order_number' => fake()->numberBetween(1, 5),
            'type'         => fake()->randomElement(['text', 'image', 'audio']),
            'content'      => fake()->paragraph,
        ];
    }
}
