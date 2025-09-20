<?php

namespace Database\Factories;

use App\Models\Activity;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Activity>
 */
class ActivityFactory extends Factory
{
    protected $model = Activity::class;

    public function definition(): array
    {
        return [
            'type' => 'server.created',
            'description' => 'Server created',
            'causer_id' => null,
            'subject_type' => null,
            'subject_id' => null,
            'properties' => ['name' => $this->faker->domainWord().' server', 'public_ip' => $this->faker->ipv4(), 'ssh_port' => 22],
        ];
    }
}
