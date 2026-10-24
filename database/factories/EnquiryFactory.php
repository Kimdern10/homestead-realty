<?php

namespace Database\Factories;

use App\Enums\EnquiryStatus;
use App\Enums\EnquiryType;
use App\Models\Enquiry;
use App\Models\Listing;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Enquiry>
 */
class EnquiryFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'type' => EnquiryType::Message,
            'status' => EnquiryStatus::New,
            'listing_id' => Listing::factory(),
            'agent_id' => fn (array $attributes) => Listing::query()->find($attributes['listing_id'])?->agent_id,
            'name' => fake()->name(),
            'email' => fake()->safeEmail(),
            'phone' => fake()->numerify('(512) 555-####'),
            'message' => fake()->sentence(12),
            'ip_address' => fake()->ipv4(),
        ];
    }

    /**
     * A tour request.
     */
    public function tour(): static
    {
        return $this->state(fn (array $attributes) => [
            'type' => EnquiryType::Tour,
            'tour_date' => now()->addDays(3)->toDateString(),
            'tour_time' => 'morning',
            'tour_kind' => 'in_person',
        ]);
    }
}
