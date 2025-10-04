<?php

namespace Database\Factories;

use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\ServerMonitoring>
 */
class ServerMonitoringFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'server_id' => \App\Models\Server::factory(),
            'status' => fake()->randomElement(['installing', 'active', 'failed', 'uninstalling', 'uninstalled']),
            'collection_interval' => 300,
            'installed_at' => now(),
            'uninstalled_at' => null,
        ];
    }
}
