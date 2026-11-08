<?php

namespace Database\Factories;

use App\Models\Post;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/**
 * @extends Factory<Post>
 */
class PostFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $title = rtrim(fake()->unique()->sentence(5), '.');

        return [
            'title' => $title,
            'slug' => Str::slug($title),
            'excerpt' => fake()->sentence(14),
            'body' => fake()->paragraphs(4, true),
            'is_published' => true,
            'published_at' => now()->subDay(),
        ];
    }

    /**
     * A draft that is not on the website yet.
     */
    public function draft(): static
    {
        return $this->state(fn (array $attributes) => ['is_published' => false, 'published_at' => null]);
    }
}
