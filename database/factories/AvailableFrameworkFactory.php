<?php

namespace Database\Factories;

use App\Models\AvailableFramework;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\AvailableFramework>
 */
class AvailableFrameworkFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $slug = $this->faker->unique()->slug(2);

        return [
            'name' => 'Framework '.ucfirst($this->faker->word()),
            'slug' => $slug,
            'public_directory' => '/public',
            'env' => [
                'file_path' => '.env',
                'supports' => true,
            ],
            'requirements' => [
                'database' => true,
                'redis' => true,
                'nodejs' => true,
                'composer' => true,
            ],
            'description' => 'A test framework',
        ];
    }

    /**
     * Indicate that the framework is Laravel.
     */
    public function laravel(): static
    {
        return $this->state(fn (array $attributes) => [
            'name' => 'Laravel',
            'slug' => AvailableFramework::LARAVEL,
            'public_directory' => '/public',
            'env' => [
                'file_path' => '.env',
                'supports' => true,
            ],
            'requirements' => [
                'database' => true,
                'redis' => true,
                'nodejs' => true,
                'composer' => true,
            ],
            'description' => 'Laravel PHP framework with full-stack capabilities',
        ]);
    }

    /**
     * Indicate that the framework is WordPress.
     */
    public function wordpress(): static
    {
        return $this->state(fn (array $attributes) => [
            'name' => 'WordPress',
            'slug' => AvailableFramework::WORDPRESS,
            'public_directory' => '',
            'env' => [
                'file_path' => 'wp-config.php',
                'supports' => true,
            ],
            'requirements' => [
                'database' => true,
                'redis' => false,
                'nodejs' => false,
                'composer' => false,
            ],
            'description' => 'WordPress CMS with PHP and MySQL',
        ]);
    }

    /**
     * Indicate that the framework is Generic PHP.
     */
    public function genericPhp(): static
    {
        return $this->state(fn (array $attributes) => [
            'name' => 'Generic PHP',
            'slug' => AvailableFramework::GENERIC_PHP,
            'public_directory' => '/public',
            'env' => [
                'file_path' => '.env',
                'supports' => true,
            ],
            'requirements' => [
                'database' => false,
                'redis' => false,
                'nodejs' => false,
                'composer' => true,
            ],
            'description' => 'Generic PHP application with Composer',
        ]);
    }

    /**
     * Indicate that the framework is Static HTML.
     */
    public function staticHtml(): static
    {
        return $this->state(fn (array $attributes) => [
            'name' => 'Static HTML',
            'slug' => AvailableFramework::STATIC_HTML,
            'public_directory' => '/public',
            'env' => [
                'file_path' => null,
                'supports' => false,
            ],
            'requirements' => [
                'database' => false,
                'redis' => false,
                'nodejs' => false,
                'composer' => false,
            ],
            'description' => 'Static HTML/CSS/JS website',
        ]);
    }
}
