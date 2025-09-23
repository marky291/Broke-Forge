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
}
