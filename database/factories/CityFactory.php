<?php

namespace Database\Factories;

use App\Models\City;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/**
 * @extends Factory<City>
 */
class CityFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $name = fake()->unique()->city();

        return [
            'name' => $name,
            'slug' => Str::slug($name),
            'state' => 'Texas',
            'state_code' => 'TX',
            'tagline' => fake()->sentence(),
            'description' => fake()->paragraphs(2, true),
            'latitude' => fake()->latitude(30, 33),
            'longitude' => fake()->longitude(-98, -95),
            'is_featured' => true,
            'sort_order' => 0,
        ];
    }
}
