<?php

namespace Database\Factories;

use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\ServerService>
 */
class ServerServiceFactory extends Factory
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
            'service_name' => $this->faker->randomElement(['php', 'nginx', 'mysql', 'postgresql', 'redis']),
            'service_type' => $this->faker->randomElement(['runtime', 'web_server', 'database', 'cache']),
            'configuration' => ['version' => $this->faker->randomElement(['8.1', '8.2', '8.3', '8.4'])],
            'status' => $this->faker->randomElement(['pending', 'installing', 'installed', 'failed', 'uninstalling']),
            'progress_step' => $this->faker->numberBetween(0, 10),
            'progress_total' => $this->faker->numberBetween(5, 15),
            'progress_label' => $this->faker->sentence(3),
            'installed_at' => $this->faker->optional()->dateTimeBetween('-1 year', 'now'),
            'uninstalled_at' => null,
        ];
    }
}
