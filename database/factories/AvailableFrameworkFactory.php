<?php

namespace Database\Factories;

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
            'name' => 'Laravel '.ucfirst($this->faker->word()),
            'slug' => $slug,
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
        ];
    }

    /**
     * Indicate that the framework is WordPress.
     */
    public function wordpress(): static
    {
        return $this->state(fn (array $attributes) => [
            'name' => 'WordPress',
            'slug' => 'wordpress',
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
            'slug' => 'generic-php',
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
            'slug' => 'static-html',
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
