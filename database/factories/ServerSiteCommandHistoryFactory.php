<?php

namespace Database\Factories;

use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\ServerSiteCommandHistory>
 */
class ServerSiteCommandHistoryFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $commands = [
            'php artisan migrate',
            'composer install',
            'npm install',
            'php artisan cache:clear',
            'php artisan config:cache',
            'git pull origin main',
        ];

        return [
            'server_id' => \App\Models\Server::factory(),
            'server_site_id' => \App\Models\ServerSite::factory(),
            'command' => fake()->randomElement($commands),
            'output' => fake()->text(200),
            'error_output' => null,
            'exit_code' => 0,
            'duration_ms' => fake()->numberBetween(100, 5000),
            'success' => true,
        ];
    }

    /**
     * Indicate that the command failed.
     */
    public function failed(): static
    {
        return $this->state(fn (array $attributes) => [
            'output' => 'Command execution started...',
            'error_output' => 'Error: '.fake()->sentence(),
            'exit_code' => fake()->numberBetween(1, 255),
            'success' => false,
        ]);
    }

    /**
     * Indicate that the command succeeded with no output.
     */
    public function noOutput(): static
    {
        return $this->state(fn (array $attributes) => [
            'output' => '',
            'error_output' => null,
            'exit_code' => 0,
            'success' => true,
        ]);
    }

    /**
     * Indicate that the command took a long time.
     */
    public function slow(): static
    {
        return $this->state(fn (array $attributes) => [
            'duration_ms' => fake()->numberBetween(30000, 120000), // 30s - 2min
        ]);
    }
}
