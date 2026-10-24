<?php

namespace Database\Factories;

use App\Enums\ListingStatus;
use App\Enums\PropertyType;
use App\Models\Agent;
use App\Models\City;
use App\Models\Listing;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/**
 * @extends Factory<Listing>
 */
class ListingFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $address = fake()->streetAddress();

        return [
            'agent_id' => Agent::factory(),
            'city_id' => City::factory(),
            'title' => 'Lovely home on '.Str::after($address, ' '),
            'slug' => Str::slug($address).'-'.Str::lower(Str::random(5)),
            'status' => ListingStatus::ForSale,
            'property_type' => PropertyType::House,
            'price' => fake()->numberBetween(25, 150) * 10000,
            'beds' => fake()->numberBetween(1, 5),
            'baths' => fake()->randomElement([1, 1.5, 2, 2.5, 3]),
            'sqft' => fake()->numberBetween(700, 4000),
            'lot_acres' => fake()->randomFloat(2, 0.1, 1),
            'year_built' => fake()->numberBetween(1950, 2024),
            'garage_spaces' => fake()->numberBetween(0, 3),
            'address' => $address,
            'neighborhood' => fake()->randomElement(['Downtown', 'Riverside', 'Oak Hill', 'Westlake']),
            'zip' => fake()->numerify('787##'),
            'latitude' => fake()->latitude(30.2, 30.4),
            'longitude' => fake()->longitude(-97.8, -97.6),
            'description' => fake()->paragraphs(3, true),
            'amenities' => fake()->randomElements(Listing::AMENITIES, 5),
            'hoa_fee' => null,
            'property_tax' => null,
            'is_featured' => false,
            'is_published' => true,
            'listed_at' => now()->subDays(fake()->numberBetween(1, 60)),
        ];
    }

    /**
     * A home for rent (monthly price).
     */
    public function forRent(): static
    {
        return $this->state(fn (array $attributes) => [
            'status' => ListingStatus::ForRent,
            'property_type' => PropertyType::Apartment,
            'price' => fake()->numberBetween(12, 60) * 100,
        ]);
    }

    /**
     * A home that has sold.
     */
    public function sold(): static
    {
        return $this->state(fn (array $attributes) => [
            'status' => ListingStatus::Sold,
            'sold_at' => now()->subDays(fake()->numberBetween(5, 90)),
        ]);
    }

    /**
     * A listing hidden from the website.
     */
    public function draft(): static
    {
        return $this->state(fn (array $attributes) => ['is_published' => false]);
    }

    /**
     * Shown in the featured row on the home page.
     */
    public function featured(): static
    {
        return $this->state(fn (array $attributes) => ['is_featured' => true]);
    }
}
