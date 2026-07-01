<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Models\FoodTruck;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<FoodTruck>
 */
class FoodTruckFactory extends Factory
{
    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'user_id' => User::factory(),
            'name' => fake()->company().' Eats',
            'description' => fake()->sentence(),
            'is_published' => false,
        ];
    }

    public function published(): static
    {
        return $this->state(['is_published' => true]);
    }
}
