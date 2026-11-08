<?php

namespace Database\Factories;

use App\Models\Agent;
use App\Models\Review;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Review>
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
            'agent_id' => Agent::factory(),
            'name' => fake()->name(),
            'email' => fake()->safeEmail(),
            'rating' => fake()->numberBetween(4, 5),
            'title' => fake()->sentence(4),
            'body' => fake()->paragraph(),
            'status' => Review::PENDING,
            'ip_address' => fake()->ipv4(),
        ];
    }

    /**
     * A review the admin has approved.
     */
    public function approved(): static
    {
        return $this->state(fn (array $attributes) => ['status' => Review::APPROVED]);
    }
}
