<?php

namespace Database\Factories;

use App\Models\Server;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\ServerSupervisorTask>
 */
class ServerSupervisorTaskFactory extends Factory
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
            'name' => fake()->words(2, true),
            'command' => fake()->randomElement([
                'php artisan queue:work',
                'php artisan horizon',
                'npm run dev',
                'node server.js',
            ]),
            'working_directory' => '/home/brokeforge',
            'processes' => fake()->numberBetween(1, 4),
            'user' => 'brokeforge',
            'auto_restart' => true,
            'autorestart_unexpected' => true,
            'status' => 'active',
            'stdout_logfile' => null,
            'stderr_logfile' => null,
            'installed_at' => now(),
            'uninstalled_at' => null,
        ];
    }

    /**
     * Indicate that the task is active
     */
    public function active(): static
    {
        return $this->state(fn (array $attributes) => [
            'status' => 'active',
        ]);
    }

    /**
     * Indicate that the task is inactive
     */
    public function inactive(): static
    {
        return $this->state(fn (array $attributes) => [
            'status' => 'inactive',
        ]);
    }

    /**
     * Indicate that the task failed
     */
    public function failed(): static
    {
        return $this->state(fn (array $attributes) => [
            'status' => 'failed',
        ]);
    }

    /**
     * Set specific command
     */
    public function command(string $command): static
    {
        return $this->state(fn (array $attributes) => [
            'command' => $command,
        ]);
    }

    /**
     * Set specific user
     */
    public function user(string $user): static
    {
        return $this->state(fn (array $attributes) => [
            'user' => $user,
        ]);
    }
}
