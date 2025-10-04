<?php

namespace Database\Factories;

use App\Enums\ScheduleFrequency;
use App\Enums\TaskStatus;
use App\Models\Server;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\ServerScheduledTask>
 */
class ServerScheduledTaskFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $frequency = fake()->randomElement(ScheduleFrequency::cases());

        return [
            'server_id' => Server::factory(),
            'name' => fake()->words(3, true),
            'command' => fake()->randomElement([
                'apt-get autoremove && apt-get autoclean',
                'php /home/brokeforge/artisan schedule:run',
                'certbot renew --quiet',
                'tar -czf /backups/db-backup-$(date +%Y%m%d).tar.gz /var/lib/mysql',
            ]),
            'frequency' => $frequency,
            'cron_expression' => $frequency === ScheduleFrequency::Custom ? '*/15 * * * *' : null,
            'status' => TaskStatus::Active,
            'last_run_at' => fake()->optional()->dateTimeBetween('-1 day', 'now'),
            'next_run_at' => fake()->optional()->dateTimeBetween('now', '+1 day'),
            'send_notifications' => fake()->boolean(30),
            'timeout' => fake()->randomElement([60, 300, 600, 1800, 3600]),
        ];
    }

    /**
     * Indicate that the task is active
     */
    public function active(): static
    {
        return $this->state(fn (array $attributes) => [
            'status' => TaskStatus::Active,
        ]);
    }

    /**
     * Indicate that the task is paused
     */
    public function paused(): static
    {
        return $this->state(fn (array $attributes) => [
            'status' => TaskStatus::Paused,
        ]);
    }

    /**
     * Indicate that the task runs daily
     */
    public function daily(): static
    {
        return $this->state(fn (array $attributes) => [
            'frequency' => ScheduleFrequency::Daily,
            'cron_expression' => null,
        ]);
    }

    /**
     * Indicate that the task runs hourly
     */
    public function hourly(): static
    {
        return $this->state(fn (array $attributes) => [
            'frequency' => ScheduleFrequency::Hourly,
            'cron_expression' => null,
        ]);
    }

    /**
     * Indicate that the task uses a custom cron expression
     */
    public function custom(string $cronExpression = '*/15 * * * *'): static
    {
        return $this->state(fn (array $attributes) => [
            'frequency' => ScheduleFrequency::Custom,
            'cron_expression' => $cronExpression,
        ]);
    }
}
