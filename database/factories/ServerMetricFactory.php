<?php

namespace Database\Factories;

use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\ServerMetric>
 */
class ServerMetricFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $memoryTotal = fake()->numberBetween(2000, 64000);
        $memoryUsed = fake()->numberBetween(500, $memoryTotal);
        $memoryPercentage = ($memoryUsed / $memoryTotal) * 100;

        $storageTotal = fake()->numberBetween(20, 500);
        $storageUsed = fake()->numberBetween(5, $storageTotal);
        $storagePercentage = ($storageUsed / $storageTotal) * 100;

        return [
            'server_id' => \App\Models\Server::factory(),
            'cpu_usage' => fake()->randomFloat(2, 0, 100),
            'memory_total_mb' => $memoryTotal,
            'memory_used_mb' => $memoryUsed,
            'memory_usage_percentage' => round($memoryPercentage, 2),
            'storage_total_gb' => $storageTotal,
            'storage_used_gb' => $storageUsed,
            'storage_usage_percentage' => round($storagePercentage, 2),
            'collected_at' => now(),
        ];
    }
}
