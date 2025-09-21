<?php

namespace Database\Factories;

use App\Models\Server;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/**
 * @extends Factory<Server>
 */
class ServerFactory extends Factory
{
    protected $model = Server::class;

    public function definition(): array
    {
        $appUser = Str::slug($this->faker->domainWord());

        return [
            'user_id' => User::factory(),
            'vanity_name' => $this->faker->domainWord().' server',
            'public_ip' => $this->faker->ipv4(),
            'private_ip' => $this->faker->optional()->ipv4(),
            'ssh_port' => 22,
            'ssh_root_user' => 'root',
            'ssh_app_user' => $appUser,
            'connection' => 'connected',
        ];
    }
}
