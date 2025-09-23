<?php

namespace Database\Factories;

use App\Models\Server;
use App\Models\ServerPackageEvent;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<ServerPackageEvent>
 */
class ServerPackageEventFactory extends Factory
{
    protected $model = ServerPackageEvent::class;

    public function definition(): array
    {
        $totalSteps = $this->faker->numberBetween(3, 10);
        $currentStep = $this->faker->numberBetween(1, $totalSteps);

        $status = $this->faker->randomElement(['pending', 'success', 'failed']);

        return [
            'server_id' => Server::factory(),
            'service_type' => $this->faker->randomElement(['mysql', 'postgresql', 'nginx', 'apache', 'redis']),
            'provision_type' => $this->faker->randomElement(['install', 'uninstall']),
            'milestone' => $this->faker->sentence(3),
            'current_step' => $currentStep,
            'total_steps' => $totalSteps,
            'details' => [
                'message' => $this->faker->sentence(),
                'command' => $this->faker->optional()->word(),
                'output' => $this->faker->optional()->text(50),
            ],
            'status' => $status,
            'error_log' => $status === 'failed' ? $this->faker->text(200) : null,
        ];
    }

    /**
     * Indicate that the event is for installation.
     */
    public function install(): static
    {
        return $this->state(fn (array $attributes) => [
            'provision_type' => 'install',
        ]);
    }

    /**
     * Indicate that the event is for uninstallation.
     */
    public function uninstall(): static
    {
        return $this->state(fn (array $attributes) => [
            'provision_type' => 'uninstall',
        ]);
    }

    /**
     * Indicate that the event is completed.
     */
    public function completed(): static
    {
        return $this->state(function (array $attributes) {
            $totalSteps = $attributes['total_steps'] ?? $this->faker->numberBetween(3, 10);

            return [
                'current_step' => $totalSteps,
                'total_steps' => $totalSteps,
            ];
        });
    }

    /**
     * Indicate that the event is at the beginning.
     */
    public function started(): static
    {
        return $this->state(fn (array $attributes) => [
            'current_step' => 1,
        ]);
    }

    /**
     * Set a specific progress percentage.
     */
    public function withProgress(int $currentStep, int $totalSteps): static
    {
        return $this->state(fn (array $attributes) => [
            'current_step' => $currentStep,
            'total_steps' => $totalSteps,
        ]);
    }

    /**
     * Indicate that the event is pending.
     */
    public function pending(): static
    {
        return $this->state(fn (array $attributes) => [
            'status' => 'pending',
            'error_log' => null,
        ]);
    }

    /**
     * Indicate that the event was successful.
     */
    public function success(): static
    {
        return $this->state(fn (array $attributes) => [
            'status' => 'success',
            'error_log' => null,
        ]);
    }

    /**
     * Indicate that the event failed with an error.
     */
    public function failed(string $errorLog = null): static
    {
        return $this->state(fn (array $attributes) => [
            'status' => 'failed',
            'error_log' => $errorLog ?? $this->faker->text(200),
        ]);
    }
}
