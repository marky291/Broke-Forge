<?php

namespace Database\Factories;

use App\Enums\TaskStatus;
use App\Models\ServerDatabase;
use App\Models\ServerDatabaseSchema;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<ServerDatabaseSchema>
 */
class ServerDatabaseSchemaFactory extends Factory
{
    protected $model = ServerDatabaseSchema::class;

    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'server_database_id' => ServerDatabase::factory(),
            'name' => $this->faker->unique()->lexify('schema_??????'),
            'character_set' => 'utf8mb4',
            'collation' => 'utf8mb4_unicode_ci',
            'status' => TaskStatus::Active,
        ];
    }
}
