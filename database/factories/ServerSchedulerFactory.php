<?php

namespace Database\Factories;

use App\Models\Server;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\ServerScheduler>
 */
class ServerSchedulerFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'server_id' => Server::factory(),
            'status' => fake()->randomElement(['installing', 'active', 'failed', 'removing', null]),
            'installed_at' => now(),
            'uninstalled_at' => null,
        ];
    }

    /**
     * Indicate that the scheduler is active
     */
    public function active(): static
    {
        return $this->state(fn (array $attributes) => [
            'status' => 'active',
            'installed_at' => now(),
            'uninstalled_at' => null,
        ]);
    }

    /**
     * Indicate that the scheduler is installing
     */
    public function installing(): static
    {
        return $this->state(fn (array $attributes) => [
            'status' => 'installing',
            'installed_at' => null,
            'uninstalled_at' => null,
        ]);
    }

    /**
     * Indicate that the scheduler is uninstalled
     */
    public function uninstalled(): static
    {
        return $this->state(fn (array $attributes) => [
            'status' => null,
            'installed_at' => null,
            'uninstalled_at' => now(),
        ]);
    }
}
