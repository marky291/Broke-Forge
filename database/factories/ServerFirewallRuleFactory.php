<?php

namespace Database\Factories;

use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\ServerFirewallRule>
 */
class ServerFirewallRuleFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'name' => fake()->words(2, true),
            'port' => fake()->numberBetween(1, 65535),
            'from_ip_address' => fake()->ipv4(),
            'rule_type' => fake()->randomElement(['allow', 'deny']),
            'status' => fake()->randomElement(['pending', 'installing', 'active', 'failed']),
        ];
    }
}
