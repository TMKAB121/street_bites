<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Models\Post;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Post>
 */
class PostFactory extends Factory
{
    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'user_id' => User::factory(),
            'title' => rtrim(fake()->sentence(4), '.'),
            // Real Markdown, so rendering assertions are meaningful.
            'body' => '## '.rtrim(fake()->sentence(3), '.')."\n\n"
                .fake()->paragraph().' **'.fake()->words(2, true).'** '
                .fake()->paragraph()."\n\n- ".fake()->words(3, true)
                ."\n- ".fake()->words(3, true),
            'is_published' => false,
        ];
    }

    public function published(): static
    {
        return $this->state([
            'is_published' => true,
            'published_at' => now(),
        ]);
    }

    /** Make the post an event: an upcoming date plus a location label. */
    public function event(): static
    {
        return $this->state([
            'event_date' => now()->addWeek()->toDateString(),
            'event_location' => fake()->streetName(),
        ]);
    }
}
