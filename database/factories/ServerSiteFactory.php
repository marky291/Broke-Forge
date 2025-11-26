<?php

namespace Database\Factories;

use App\Enums\TaskStatus;
use App\Models\AvailableFramework;
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
            'available_framework_id' => AvailableFramework::factory(),
            'domain' => $this->faker->unique()->domainName(),
            'document_root' => '/var/www/'.$this->faker->slug(2).'/public',
            'php_version' => $this->faker->randomElement(['8.1', '8.2', '8.3']),
            'ssl_enabled' => $this->faker->boolean(70),
            'ssl_cert_path' => null,
            'ssl_key_path' => null,
            'nginx_config_path' => '/etc/nginx/sites-available/'.$this->faker->slug(2),
            'status' => $this->faker->randomElement(['active', 'installing', 'failed']),
            'git_status' => null,
            'configuration' => [],
            'installed_at' => $this->faker->dateTimeBetween('-6 months', 'now'),
            'git_installed_at' => null,
            'last_deployment_sha' => null,
            'last_deployed_at' => null,
            'uninstalled_at' => null,
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
            'installed_at' => now(),
        ]);
    }

    /**
     * Indicate that the site has Git installed.
     */
    public function withGit(): static
    {
        return $this->state(fn (array $attributes) => [
            'git_status' => TaskStatus::Success,
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
            'git_status' => TaskStatus::Installing,
        ]);
    }

    /**
     * Indicate that Git installation failed.
     */
    public function gitFailed(): static
    {
        return $this->state(fn (array $attributes) => [
            'git_status' => TaskStatus::Failed,
        ]);
    }

    /**
     * Indicate that the site uses the Laravel framework.
     */
    public function laravel(): static
    {
        return $this->state(fn (array $attributes) => [
            'available_framework_id' => AvailableFramework::factory()->laravel(),
            'document_root' => '/home/brokeforge/'.$this->faker->domainName().'/public',
        ]);
    }

    /**
     * Indicate that the site uses the WordPress framework.
     */
    public function wordpress(): static
    {
        return $this->state(fn (array $attributes) => [
            'available_framework_id' => AvailableFramework::factory()->wordpress(),
            'document_root' => '/home/brokeforge/'.$this->faker->domainName(),
            'php_version' => $this->faker->randomElement(['8.1', '8.2', '8.3']),
        ]);
    }

    /**
     * Indicate that the site uses the Generic PHP framework.
     */
    public function genericPhp(): static
    {
        return $this->state(fn (array $attributes) => [
            'available_framework_id' => AvailableFramework::factory()->genericPhp(),
            'document_root' => '/home/brokeforge/'.$this->faker->domainName().'/public',
        ]);
    }

    /**
     * Indicate that the site uses the Static HTML framework.
     */
    public function staticHtml(): static
    {
        return $this->state(fn (array $attributes) => [
            'available_framework_id' => AvailableFramework::factory()->staticHtml(),
            'document_root' => '/home/brokeforge/'.$this->faker->domainName().'/public',
            'php_version' => null,
        ]);
    }
}
