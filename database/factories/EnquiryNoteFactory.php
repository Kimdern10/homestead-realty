<?php

namespace Database\Factories;

use App\Models\Enquiry;
use App\Models\EnquiryNote;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<EnquiryNote>
 */
class EnquiryNoteFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'enquiry_id' => Enquiry::factory(),
            'body' => fake()->sentence(),
        ];
    }
}
