<?php

namespace Database\Factories;

use App\Enums\TaskStatus;
use App\Models\Server;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\ServerNode>
 */
class ServerNodeFactory extends Factory
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
            'version' => fake()->randomElement(['22', '20', '18', '16']),
            'is_default' => false,
            'status' => TaskStatus::Active,
        ];
    }
}
