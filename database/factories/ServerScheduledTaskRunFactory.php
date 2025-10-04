<?php

namespace Database\Factories;

use App\Models\Server;
use App\Models\ServerScheduledTask;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\ServerScheduledTaskRun>
 */
class ServerScheduledTaskRunFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $startedAt = fake()->dateTimeBetween('-7 days', 'now');
        $durationMs = fake()->numberBetween(100, 30000);
        $exitCode = fake()->optional(0.8, 1)->passthrough(0);

        return [
            'server_id' => Server::factory(),
            'server_scheduled_task_id' => ServerScheduledTask::factory(),
            'started_at' => $startedAt,
            'completed_at' => (clone $startedAt)->modify("+{$durationMs} milliseconds"),
            'exit_code' => $exitCode,
            'output' => $exitCode === 0 ? fake()->optional()->sentence() : null,
            'error_output' => $exitCode !== 0 ? fake()->sentence() : null,
            'duration_ms' => $durationMs,
        ];
    }

    /**
     * Indicate that the task run was successful
     */
    public function successful(): static
    {
        return $this->state(fn (array $attributes) => [
            'exit_code' => 0,
            'output' => fake()->sentence(),
            'error_output' => null,
        ]);
    }

    /**
     * Indicate that the task run failed
     */
    public function failed(): static
    {
        return $this->state(fn (array $attributes) => [
            'exit_code' => fake()->randomElement([1, 2, 126, 127, 130]),
            'output' => null,
            'error_output' => fake()->sentence(),
        ]);
    }

    /**
     * Indicate that the task run is still running
     */
    public function running(): static
    {
        return $this->state(fn (array $attributes) => [
            'completed_at' => null,
            'exit_code' => null,
            'output' => null,
            'error_output' => null,
            'duration_ms' => null,
        ]);
    }
}
