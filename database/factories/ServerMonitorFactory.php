<?php

namespace Database\Factories;

use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\ServerMonitor>
 */
class ServerMonitorFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $metricType = $this->faker->randomElement(['cpu', 'memory', 'storage']);
        $operator = $this->faker->randomElement(['>', '>=']);

        return [
            'user_id' => \App\Models\User::factory(),
            'server_id' => \App\Models\Server::factory(),
            'name' => $this->faker->words(3, true).' Alert',
            'metric_type' => $metricType,
            'operator' => $operator,
            'threshold' => $this->faker->randomFloat(2, 70, 95),
            'duration_minutes' => $this->faker->randomElement([5, 10, 15, 30, 60]),
            'notification_emails' => [$this->faker->email()],
            'enabled' => true,
            'cooldown_minutes' => 60,
            'status' => 'normal',
        ];
    }

    public function triggered(): static
    {
        return $this->state(fn (array $attributes) => [
            'status' => 'triggered',
            'last_triggered_at' => now(),
        ]);
    }

    public function disabled(): static
    {
        return $this->state(fn (array $attributes) => [
            'enabled' => false,
        ]);
    }
}
