<?php

namespace Database\Factories;

use App\Enums\DatabaseStatus;
use App\Enums\DatabaseType;
use App\Models\Server;
use App\Models\ServerDatabase;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<ServerDatabase>
 */
class ServerDatabaseFactory extends Factory
{
    protected $model = ServerDatabase::class;

    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'server_id' => Server::factory(),
            'name' => $this->faker->randomElement(['mysql', 'postgresql', 'mariadb']),
            'type' => $this->faker->randomElement(DatabaseType::cases()),
            'version' => $this->faker->randomElement(['8.0', '8.4', '5.7', '14', '15']),
            'port' => $this->faker->randomElement([3306, 5432, 3307]),
            'status' => DatabaseStatus::Active,
            'root_password' => $this->faker->password(16),
        ];
    }
}
