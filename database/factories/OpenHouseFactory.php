<?php

namespace Database\Factories;

use App\Models\Listing;
use App\Models\OpenHouse;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<OpenHouse>
 */
class OpenHouseFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $start = now()->addDays(fake()->numberBetween(1, 10))->setTime(13, 0);

        return [
            'listing_id' => Listing::factory(),
            'starts_at' => $start,
            'ends_at' => $start->copy()->addHours(2),
            'note' => null,
        ];
    }
}
