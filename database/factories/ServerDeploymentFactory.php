<?php

namespace Database\Factories;

use App\Models\Server;
use App\Models\ServerSite;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * Factory for creating ServerDeployment model instances.
 *
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\ServerDeployment>
 */
class ServerDeploymentFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $domain = $this->faker->domainName();
        $timestamp = now()->format('dmY-His'); // ddMMYYYY-HHMMSS format

        return [
            'server_id' => Server::factory(),
            'server_site_id' => ServerSite::factory(),
            'status' => 'success',
            'deployment_script' => "git pull origin main\ncomposer install --no-interaction\nphp artisan migrate --force",
            'log_file_path' => "/home/brokeforge/deployments/{$domain}/{$timestamp}/deployment.log",
            'triggered_by' => 'manual',
            'exit_code' => 0,
            'commit_sha' => $this->faker->sha1(),
            'branch' => 'main',
            'duration_ms' => $this->faker->numberBetween(1000, 60000),
            'started_at' => now()->subMinutes(5),
            'completed_at' => now(),
        ];
    }

    /**
     * Indicate that the deployment is pending.
     */
    public function pending(): static
    {
        return $this->state(fn (array $attributes) => [
            'status' => 'pending',
            'log_file_path' => null,
            'exit_code' => null,
            'commit_sha' => null,
            'duration_ms' => null,
            'started_at' => null,
            'completed_at' => null,
        ]);
    }

    /**
     * Indicate that the deployment is running.
     */
    public function running(): static
    {
        return $this->state(fn (array $attributes) => [
            'status' => 'updating',
            'exit_code' => null,
            'duration_ms' => null,
            'started_at' => now(),
            'completed_at' => null,
        ]);
    }

    /**
     * Indicate that the deployment failed.
     */
    public function failed(): static
    {
        return $this->state(fn (array $attributes) => [
            'status' => 'failed',
            'exit_code' => 1,
            'duration_ms' => $this->faker->numberBetween(1000, 30000),
            'completed_at' => now(),
        ]);
    }
}
