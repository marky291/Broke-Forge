<?php

namespace Database\Factories;

use App\Enums\GitStatus;
use App\Models\Server;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * Factory for creating ServerSite model instances.
 *
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\ServerSite>
 */
class ServerSiteFactory extends Factory
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
            'domain' => $this->faker->domainName(),
            'document_root' => '/var/www/'.$this->faker->slug(2).'/public',
            'php_version' => $this->faker->randomElement(['8.1', '8.2', '8.3']),
            'ssl_enabled' => $this->faker->boolean(70),
            'ssl_cert_path' => null,
            'ssl_key_path' => null,
            'nginx_config_path' => '/etc/nginx/sites-available/'.$this->faker->slug(2),
            'status' => $this->faker->randomElement(['active', 'provisioning', 'failed']),
            'git_status' => null,
            'configuration' => [],
            'provisioned_at' => $this->faker->dateTimeBetween('-6 months', 'now'),
            'git_installed_at' => null,
            'last_deployment_sha' => null,
            'last_deployed_at' => null,
            'deprovisioned_at' => null,
        ];
    }

    /**
     * Indicate that the site has SSL enabled.
     */
    public function withSSL(): static
    {
        return $this->state(fn (array $attributes) => [
            'ssl_enabled' => true,
            'ssl_cert_path' => '/etc/ssl/certs/'.$attributes['domain'].'.crt',
            'ssl_key_path' => '/etc/ssl/private/'.$attributes['domain'].'.key',
        ]);
    }

    /**
     * Indicate that the site is active.
     */
    public function active(): static
    {
        return $this->state(fn (array $attributes) => [
            'status' => 'active',
            'provisioned_at' => now(),
        ]);
    }

    /**
     * Indicate that the site has Git installed.
     */
    public function withGit(): static
    {
        return $this->state(fn (array $attributes) => [
            'git_status' => GitStatus::Installed,
            'git_installed_at' => now(),
            'configuration' => [
                'git_repository' => [
                    'provider' => 'github',
                    'repository' => 'laravel/laravel',
                    'branch' => 'main',
                ],
            ],
        ]);
    }

    /**
     * Indicate that Git is being installed.
     */
    public function gitInstalling(): static
    {
        return $this->state(fn (array $attributes) => [
            'git_status' => GitStatus::Installing,
        ]);
    }

    /**
     * Indicate that Git installation failed.
     */
    public function gitFailed(): static
    {
        return $this->state(fn (array $attributes) => [
            'git_status' => GitStatus::Failed,
        ]);
    }
}
