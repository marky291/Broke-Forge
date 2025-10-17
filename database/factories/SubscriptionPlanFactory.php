<?php

namespace Database\Factories;

use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\SubscriptionPlan>
 */
class SubscriptionPlanFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $intervals = ['month', 'year'];
        $interval = fake()->randomElement($intervals);

        return [
            'stripe_product_id' => 'prod_'.fake()->uuid(),
            'stripe_price_id' => 'price_'.fake()->uuid(),
            'name' => fake()->randomElement(['Starter', 'Professional', 'Business', 'Enterprise']),
            'slug' => fake()->unique()->slug(),
            'amount' => fake()->randomElement([999, 1999, 4999, 9999]), // In cents
            'currency' => 'eur',
            'interval' => $interval,
            'interval_count' => 1,
            'server_limit' => fake()->randomElement([5, 10, 25, 100]),
            'features' => [
                '24/7 Support',
                'Automated Backups',
                'SSL Certificates',
            ],
            'is_active' => true,
            'sort_order' => fake()->numberBetween(1, 10),
        ];
    }

    /**
     * Indicate that the plan is inactive.
     */
    public function inactive(): static
    {
        return $this->state(fn (array $attributes) => [
            'is_active' => false,
        ]);
    }

    /**
     * Indicate that the plan is a monthly plan.
     */
    public function monthly(): static
    {
        return $this->state(fn (array $attributes) => [
            'interval' => 'month',
            'interval_count' => 1,
        ]);
    }

    /**
     * Indicate that the plan is a yearly plan.
     */
    public function yearly(): static
    {
        return $this->state(fn (array $attributes) => [
            'interval' => 'year',
            'interval_count' => 1,
        ]);
    }
}
