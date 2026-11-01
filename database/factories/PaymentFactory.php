<?php

namespace Database\Factories;

use App\Enums\PaymentPurpose;
use App\Enums\PaymentStatus;
use App\Models\Payment;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Payment>
 */
class PaymentFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'reference' => Payment::newReference(),
            'gateway' => 'stripe',
            'purpose' => PaymentPurpose::HoldingDeposit,
            'status' => PaymentStatus::Pending,
            'amount' => 50000,
            'currency' => 'USD',
            'description' => 'Holding deposit',
            'payer_name' => fake()->name(),
            'payer_email' => fake()->safeEmail(),
            'payer_phone' => '(512) 555-0100',
        ];
    }

    /**
     * A payment the provider has confirmed.
     */
    public function paid(): static
    {
        return $this->state(fn (array $attributes) => ['status' => PaymentStatus::Paid, 'paid_at' => now()]);
    }
}
