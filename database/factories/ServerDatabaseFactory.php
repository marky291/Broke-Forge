<?php

namespace Database\Factories;

use App\Enums\DatabaseEngine;
use App\Enums\StorageType;
use App\Enums\TaskStatus;
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
        $engine = $this->faker->randomElement(DatabaseEngine::cases());

        return [
            'server_id' => Server::factory(),
            'name' => $this->faker->randomElement(['mysql', 'postgresql', 'mariadb']),
            'engine' => $engine,
            'storage_type' => $engine->storageType(),
            'version' => $this->faker->randomElement(['8.0', '8.4', '5.7', '14', '15']),
            'port' => $this->faker->randomElement([3306, 5432, 3307]),
            'status' => TaskStatus::Active,
            'root_password' => $this->faker->password(16),
        ];
    }

    /**
     * Create a MySQL database.
     */
    public function mysql(): static
    {
        return $this->state(fn (array $attributes) => [
            'name' => 'mysql',
            'engine' => DatabaseEngine::MySQL,
            'storage_type' => StorageType::Disk,
            'version' => '8.0',
            'port' => 3306,
        ]);
    }

    /**
     * Create a PostgreSQL database.
     */
    public function postgresql(): static
    {
        return $this->state(fn (array $attributes) => [
            'name' => 'postgresql',
            'engine' => DatabaseEngine::PostgreSQL,
            'storage_type' => StorageType::Disk,
            'version' => '15',
            'port' => 5432,
        ]);
    }

    /**
     * Create a MariaDB database.
     */
    public function mariadb(): static
    {
        return $this->state(fn (array $attributes) => [
            'name' => 'mariadb',
            'engine' => DatabaseEngine::MariaDB,
            'storage_type' => StorageType::Disk,
            'version' => '10.11',
            'port' => 3306,
        ]);
    }

    /**
     * Create a Redis database (memory storage).
     */
    public function redis(): static
    {
        return $this->state(fn (array $attributes) => [
            'name' => 'redis',
            'engine' => DatabaseEngine::Redis,
            'storage_type' => StorageType::Memory,
            'version' => '7.2',
            'port' => 6379,
        ]);
    }

    /**
     * Create a MongoDB database.
     */
    public function mongodb(): static
    {
        return $this->state(fn (array $attributes) => [
            'name' => 'mongodb',
            'engine' => DatabaseEngine::MongoDB,
            'storage_type' => StorageType::Disk,
            'version' => '7.0',
            'port' => 27017,
        ]);
    }

    /**
     * Create an active database.
     */
    public function active(): static
    {
        return $this->state(fn (array $attributes) => [
            'status' => TaskStatus::Active,
        ]);
    }

    /**
     * Create a pending database.
     */
    public function pending(): static
    {
        return $this->state(fn (array $attributes) => [
            'status' => TaskStatus::Pending,
        ]);
    }

    /**
     * Create a failed database.
     */
    public function failed(): static
    {
        return $this->state(fn (array $attributes) => [
            'status' => TaskStatus::Failed,
        ]);
    }
}
