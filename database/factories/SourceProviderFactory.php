<?php

namespace Database\Factories;

use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\SourceProvider>
 */
class SourceProviderFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'user_id' => User::factory(),
            'provider' => 'github',
            'provider_user_id' => fake()->randomNumber(8),
            'username' => fake()->userName(),
            'email' => fake()->safeEmail(),
            'access_token' => 'ghp_'.fake()->regexify('[A-Za-z0-9]{36}'),
        ];
    }

    /**
     * Configure the factory for GitHub provider.
     */
    public function github(): static
    {
        return $this->state(fn (array $attributes) => [
            'provider' => 'github',
            'access_token' => 'ghp_'.fake()->regexify('[A-Za-z0-9]{36}'),
        ]);
    }
}
