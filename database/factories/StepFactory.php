<?php

namespace Database\Factories;

use App\Models\Riddle;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\Step>
 */
class StepFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'riddle_id'    => Riddle::factory(),
            'order_number' => fake()->numberBetween(1, 10),
            'qr_code'      => fake()->uuid,
            'latitude'     => fake()->latitude,
            'longitude'    => fake()->longitude,
        ];
    }
}
