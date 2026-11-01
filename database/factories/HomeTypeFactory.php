<?php

namespace Database\Factories;

use App\Enums\PropertyType;
use App\Models\HomeType;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/**
 * @extends Factory<HomeType>
 */
class HomeTypeFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $name = Str::title(fake()->unique()->lexify('Type ?????'));

        return [
            'name' => $name,
            'slug' => Str::slug($name, '_'),
            'kind' => PropertyType::House,
            'sort_order' => 99,
        ];
    }
}
