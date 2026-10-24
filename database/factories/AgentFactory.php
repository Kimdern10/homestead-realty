<?php

namespace Database\Factories;

use App\Enums\UserRole;
use App\Models\Agent;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/**
 * @extends Factory<Agent>
 */
class AgentFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $name = fake()->name();

        return [
            'user_id' => User::factory()->state(['name' => $name, 'role' => UserRole::Agent]),
            'name' => $name,
            'slug' => Str::slug($name).'-'.Str::lower(Str::random(4)),
            'title' => 'Realtor®',
            'phone' => fake()->numerify('(512) 555-####'),
            'email' => fake()->unique()->safeEmail(),
            'license_number' => fake()->numerify('TX #######'),
            'years_experience' => fake()->numberBetween(2, 25),
            'languages' => 'English',
            'bio' => fake()->paragraphs(2, true),
            'is_active' => true,
        ];
    }

    /**
     * An agent hidden from the website.
     */
    public function inactive(): static
    {
        return $this->state(fn (array $attributes) => ['is_active' => false]);
    }
}
