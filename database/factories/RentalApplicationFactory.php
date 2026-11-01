<?php

namespace Database\Factories;

use App\Enums\ApplicationStatus;
use App\Models\Listing;
use App\Models\RentalApplication;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<RentalApplication>
 */
class RentalApplicationFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'listing_id' => Listing::factory()->forRent(),
            'status' => ApplicationStatus::New,
            'name' => fake()->name(),
            'email' => fake()->safeEmail(),
            'phone' => '(512) 555-0100',
            'move_in_date' => now()->addMonth()->toDateString(),
            'occupants' => 2,
            'has_pets' => false,
            'employer' => fake()->company(),
            'job_title' => fake()->jobTitle(),
            'monthly_income' => 7500,
        ];
    }
}
