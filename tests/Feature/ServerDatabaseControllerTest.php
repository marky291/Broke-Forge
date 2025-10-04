<?php

namespace Tests\Feature;

use App\Enums\DatabaseStatus;
use App\Models\Server;
use App\Models\ServerDatabase;
use App\Models\User;
use App\Packages\Services\Database\MariaDB\MariaDbInstallerJob;
use App\Packages\Services\Database\MariaDB\MariaDbRemoverJob;
use App\Packages\Services\Database\MySQL\MySqlInstallerJob;
use App\Packages\Services\Database\MySQL\MySqlRemoverJob;
use App\Packages\Services\Database\PostgreSQL\PostgreSqlInstallerJob;
use App\Packages\Services\Database\PostgreSQL\PostgreSqlRemoverJob;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Queue;
use Tests\TestCase;

class ServerDatabaseControllerTest extends TestCase
{
    use RefreshDatabase;

    public function test_it_dispatches_mysql_installer_job_for_mysql_requests(): void
    {
        Queue::fake();

        $user = User::factory()->create();
        $server = Server::factory()->create(['user_id' => $user->id]);

        $this->actingAs($user)
            ->post("/servers/{$server->id}/database", [
                'name' => 'mysql',
                'type' => 'mysql',
                'version' => '8.0',
                'port' => 3306,
                'root_password' => 'super-secure-password',
            ])
            ->assertRedirect();

        Queue::assertPushed(MySqlInstallerJob::class, fn ($job) => $job->server->is($server));
        Queue::assertNotPushed(MariaDbInstallerJob::class);
        Queue::assertNotPushed(PostgreSqlInstallerJob::class);

        $this->assertDatabaseHas('server_databases', [
            'server_id' => $server->id,
            'type' => 'mysql',
            'status' => DatabaseStatus::Installing->value,
        ]);
    }

    public function test_it_dispatches_postgresql_installer_job_for_postgresql_requests(): void
    {
        Queue::fake();

        $user = User::factory()->create();
        $server = Server::factory()->create(['user_id' => $user->id]);

        $this->actingAs($user)
            ->post("/servers/{$server->id}/database", [
                'name' => 'postgres',
                'type' => 'postgresql',
                'version' => '16',
                'port' => 5432,
                'root_password' => 'another-secure-pass',
            ])
            ->assertRedirect();

        Queue::assertPushed(PostgreSqlInstallerJob::class, fn ($job) => $job->server->is($server));
        Queue::assertNotPushed(MySqlInstallerJob::class);

        $this->assertDatabaseHas('server_databases', [
            'server_id' => $server->id,
            'type' => 'postgresql',
            'status' => DatabaseStatus::Installing->value,
        ]);
    }

    public function test_it_dispatches_correct_remover_job_during_uninstall(): void
    {
        Queue::fake();

        $user = User::factory()->create();
        $server = Server::factory()->create(['user_id' => $user->id]);

        $database = ServerDatabase::create([
            'server_id' => $server->id,
            'name' => 'mysql',
            'type' => 'mysql',
            'version' => '8.0',
            'port' => 3306,
            'status' => DatabaseStatus::Active->value,
            'root_password' => 'root-password',
        ]);

        $this->actingAs($user)
            ->delete("/servers/{$server->id}/database")
            ->assertRedirect();

        Queue::assertPushed(MySqlRemoverJob::class, fn ($job) => $job->server->is($server));
        Queue::assertNotPushed(MariaDbRemoverJob::class);
        Queue::assertNotPushed(PostgreSqlRemoverJob::class);

        $database->refresh();
        $this->assertEquals(DatabaseStatus::Uninstalling, $database->status);
    }
}
