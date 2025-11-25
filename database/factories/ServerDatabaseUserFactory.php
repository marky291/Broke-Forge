<?php

namespace Database\Factories;

use App\Enums\TaskStatus;
use App\Models\ServerDatabase;
use App\Models\ServerDatabaseUser;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<ServerDatabaseUser>
 */
class ServerDatabaseUserFactory extends Factory
{
    protected $model = ServerDatabaseUser::class;

    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'server_database_id' => ServerDatabase::factory(),
            'username' => $this->faker->unique()->lexify('user_??????'),
            'password' => $this->faker->password(16),
            'host' => '%',
            'privileges' => 'all',
            'status' => TaskStatus::Active,
        ];
    }
}
