<?php

namespace Database\Factories;

use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\PaymentMethod>
 */
class PaymentMethodFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'user_id' => \App\Models\User::factory(),
            'stripe_payment_method_id' => 'pm_'.fake()->uuid(),
            'type' => 'card',
            'brand' => fake()->randomElement(['visa', 'mastercard', 'amex', 'discover']),
            'last_four' => fake()->numberBetween(1000, 9999),
            'exp_month' => fake()->numberBetween(1, 12),
            'exp_year' => fake()->numberBetween(now()->year, now()->year + 10),
            'is_default' => false,
        ];
    }

    /**
     * Indicate that the payment method is the default.
     */
    public function isDefault(): static
    {
        return $this->state(fn (array $attributes) => [
            'is_default' => true,
        ]);
    }

    /**
     * Indicate that the payment method is expired.
     */
    public function expired(): static
    {
        return $this->state(fn (array $attributes) => [
            'exp_month' => now()->subYear()->month,
            'exp_year' => now()->subYear()->year,
        ]);
    }
}
