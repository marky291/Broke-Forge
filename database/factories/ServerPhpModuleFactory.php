<?php

namespace Database\Factories;

use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\ServerPhpModule>
 */
class ServerPhpModuleFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'server_php_id' => \App\Models\ServerPhp::factory(),
            'name' => fake()->randomElement(['gd', 'mbstring', 'curl', 'xml', 'zip', 'bcmath', 'intl', 'redis', 'memcached', 'imagick']),
            'is_enabled' => true,
        ];
    }
}
