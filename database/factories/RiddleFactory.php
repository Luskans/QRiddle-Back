<?php

namespace Database\Factories;

use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Facades\Hash;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\Riddle>
 */
class RiddleFactory extends Factory
{
    /**
     * The current password being used by the factory.
     */
    protected static ?string $password;

    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'creator_id'    => User::factory(),
            'title'         => fake()->sentence(6, true),
            'description'   => fake()->paragraph,
            'is_private'    => fake()->boolean,
            'password'      => static::$password ??= Hash::make('password'),
            'status'        => fake()->randomElement(['draft', 'active', 'disabled']),
            'latitude'     => fake()->latitude,
            'longitude'    => fake()->longitude,
        ];
    }
}
