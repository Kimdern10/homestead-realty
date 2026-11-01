<?php

namespace Database\Factories;

use App\Enums\ApplicationStatus;
use App\Models\Service;
use App\Models\ServiceBooking;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<ServiceBooking>
 */
class ServiceBookingFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'service_id' => Service::factory(),
            'service_name' => 'Home valuation',
            'name' => fake()->name(),
            'email' => fake()->safeEmail(),
            'phone' => '(512) 555-0100',
            'status' => ApplicationStatus::New,
        ];
    }
}
