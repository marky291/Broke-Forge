<?php

namespace Database\Factories;

use App\Enums\TaskStatus;
use App\Models\Server;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\ServerPhp>
 */
class ServerPhpFactory extends Factory
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
            'version' => fake()->randomElement(['8.1', '8.2', '8.3', '8.4']),
            'is_cli_default' => false,
            'is_site_default' => false,
            'status' => TaskStatus::Active,
        ];
    }
}
