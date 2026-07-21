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

    /**
     * An unclaimed listing — no owner (`user_id` null), the state an admin/import
     * seeds. The real vendor claims it later.
     */
    public function unclaimed(): static
    {
        return $this->state(['user_id' => null]);
    }

    /**
     * Give the truck a pinned GPS location (fixed coords near ZIP 66202) so
     * tests can exercise the map without caring about specific values.
     */
    public function located(): static
    {
        return $this->state([
            'latitude' => 39.0272,
            'longitude' => -94.6558,
            'location_label' => 'Johnson Dr & Nall Ave, Mission',
            'located_at' => now(),
            'timezone' => 'America/Chicago',
        ]);
    }
}
