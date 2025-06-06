<?php

namespace Database\Factories;

use App\Models\Riddle;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\Review>
 */
class ReviewFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'riddle_id' => Riddle::factory(),
            'user_id' => User::factory(),
            'content' => fake()->sentences(6, true),
            'rating' => fake()->numberBetween(1, 5),
            'difficulty' => fake()->numberBetween(1, 5),
        ];
    }
}
