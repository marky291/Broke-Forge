<?php

namespace Database\Factories;

use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\BillingEvent>
 */
class BillingEventFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $eventTypes = [
            'payment_intent.succeeded',
            'payment_intent.created',
            'customer.subscription.created',
            'customer.subscription.updated',
            'invoice.payment_succeeded',
            'charge.succeeded',
        ];

        return [
            'user_id' => \App\Models\User::factory(),
            'type' => fake()->randomElement($eventTypes),
            'stripe_event_id' => 'evt_'.fake()->uuid(),
            'metadata' => [
                'amount' => fake()->numberBetween(1000, 50000),
                'currency' => 'usd',
                'customer' => 'cus_'.fake()->uuid(),
            ],
            'description' => fake()->optional()->sentence(),
        ];
    }

    /**
     * Indicate that the event is a payment succeeded event.
     */
    public function paymentSucceeded(): static
    {
        return $this->state(fn (array $attributes) => [
            'type' => 'payment_intent.succeeded',
            'description' => 'Payment succeeded',
        ]);
    }

    /**
     * Indicate that the event is a subscription created event.
     */
    public function subscriptionCreated(): static
    {
        return $this->state(fn (array $attributes) => [
            'type' => 'customer.subscription.created',
            'description' => 'Subscription created',
        ]);
    }

    /**
     * Indicate that the event has no description.
     */
    public function noDescription(): static
    {
        return $this->state(fn (array $attributes) => [
            'description' => null,
        ]);
    }
}
