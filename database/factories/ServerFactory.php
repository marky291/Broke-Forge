<?php

namespace Database\Factories;

use App\Models\Server;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Server>
 */
class ServerFactory extends Factory
{
    protected $model = Server::class;

    public function definition(): array
    {
        return [
            'user_id' => User::factory(),
            'vanity_name' => $this->faker->domainWord().' server',
            'public_ip' => $this->faker->ipv4(),
            'private_ip' => $this->faker->optional()->ipv4(),
            'ssh_port' => 22,
            'connection' => 'connected',
            'provision' => [1 => 'installing'],
        ];
    }
}
