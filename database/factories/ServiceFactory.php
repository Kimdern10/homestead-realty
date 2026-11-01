<?php

namespace Database\Factories;

use App\Models\Service;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Service>
 */
class ServiceFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'name' => 'Home valuation',
            'description' => 'A licensed agent prices your home within 48 hours.',
            'price' => 150,
            'is_active' => true,
            'sort_order' => 0,
        ];
    }
}
