<?php

namespace Database\Factories;

use App\Enums\ReverseProxyStatus;
use App\Enums\ReverseProxyType;
use App\Models\Server;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\ServerReverseProxy>
 */
class ServerReverseProxyFactory extends Factory
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
            'type' => fake()->randomElement([ReverseProxyType::Nginx, ReverseProxyType::Apache, ReverseProxyType::Caddy]),
            'version' => fake()->randomElement(['1.24.0', '1.25.1', '2.4.57', '2.7.6']),
            'worker_processes' => fake()->randomElement(['auto', '2', '4', '8']),
            'worker_connections' => fake()->randomElement([512, 1024, 2048, 4096]),
            'status' => ReverseProxyStatus::Active,
        ];
    }
}
