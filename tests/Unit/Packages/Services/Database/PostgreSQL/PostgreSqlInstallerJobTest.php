<?php

namespace Tests\Unit\Packages\Services\Database\PostgreSQL;

use App\Enums\TaskStatus;
use App\Models\Server;
use App\Models\ServerDatabase;
use App\Models\ServerDatabaseUser;
use App\Packages\Services\Database\PostgreSQL\PostgreSQLInstallerJob;
use Exception;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class PostgreSQLInstallerJobTest extends TestCase
{
    use RefreshDatabase;

    /**
     * Test job has correct timeout property.
     */
    public function test_job_has_correct_timeout_property(): void
    {
        // Arrange
        $server = Server::factory()->create();
        $database = ServerDatabase::factory()->create(['server_id' => $server->id]);

        // Act
        $job = new PostgreSQLInstallerJob($server, $database);

        // Assert
        $this->assertEquals(600, $job->timeout);
    }

    /**
     * Test job has correct tries property.
     */
    public function test_job_has_correct_tries_property(): void
    {
        // Arrange
        $server = Server::factory()->create();
        $database = ServerDatabase::factory()->create(['server_id' => $server->id]);

        // Act
        $job = new PostgreSQLInstallerJob($server, $database);

        // Assert
        $this->assertEquals(0, $job->tries);
    }

    /**
     * Test job has correct maxExceptions property.
     */
    public function test_job_has_correct_max_exceptions_property(): void
    {
        // Arrange
        $server = Server::factory()->create();
        $database = ServerDatabase::factory()->create(['server_id' => $server->id]);

        // Act
        $job = new PostgreSQLInstallerJob($server, $database);

        // Assert
        $this->assertEquals(3, $job->maxExceptions);
    }

    /**
     * Test middleware is configured with WithoutOverlapping.
     */
    public function test_middleware_configured_with_without_overlapping(): void
    {
        // Arrange
        $server = Server::factory()->create();
        $database = ServerDatabase::factory()->create(['server_id' => $server->id]);

        // Act
        $job = new PostgreSQLInstallerJob($server, $database);
        $middleware = $job->middleware();

        // Assert
        $this->assertCount(1, $middleware);
        $this->assertInstanceOf(\Illuminate\Queue\Middleware\WithoutOverlapping::class, $middleware[0]);
    }

    /**
     * Test job constructor accepts server and database.
     */
    public function test_constructor_accepts_server_and_database(): void
    {
        // Arrange
        $server = Server::factory()->create();
        $database = ServerDatabase::factory()->create(['server_id' => $server->id]);

        // Act
        $job = new PostgreSQLInstallerJob($server, $database);

        // Assert
        $this->assertInstanceOf(PostgreSQLInstallerJob::class, $job);
        $this->assertEquals($server->id, $job->server->id);
        $this->assertEquals($database->id, $job->serverDatabase->id);
    }

    /**
     * Test failed() method updates status to TaskStatus::Failed.
     */
    public function test_failed_method_updates_status_to_failed(): void
    {
        // Arrange
        $server = Server::factory()->create();
        $database = ServerDatabase::factory()->create([
            'server_id' => $server->id,
            'status' => TaskStatus::Active,
        ]);

        $job = new PostgreSQLInstallerJob($server, $database);
        $exception = new Exception('Installation failed');

        // Act
        $job->failed($exception);

        // Assert
        $database->refresh();
        $this->assertEquals(TaskStatus::Failed, $database->status);
    }

    /**
     * Test failed() method stores error message in database.
     */
    public function test_failed_method_stores_error_log(): void
    {
        // Arrange
        $server = Server::factory()->create();
        $database = ServerDatabase::factory()->create([
            'server_id' => $server->id,
            'status' => TaskStatus::Active,
            'error_log' => null,
        ]);

        $job = new PostgreSQLInstallerJob($server, $database);
        $errorMessage = 'PostgreSQL installation timeout error';
        $exception = new Exception($errorMessage);

        // Act
        $job->failed($exception);

        // Assert
        $database->refresh();
        $this->assertEquals($errorMessage, $database->error_log);
    }

    /**
     * Test failed() method handles missing records gracefully.
     */
    public function test_failed_method_handles_missing_records_gracefully(): void
    {
        // Arrange
        $server = Server::factory()->create();
        $database = ServerDatabase::factory()->create(['server_id' => $server->id]);

        $job = new PostgreSQLInstallerJob($server, $database);
        $databaseId = $database->id;
        $database->delete(); // Now fresh() will return null

        $exception = new Exception('Test error');

        // Act - should not throw exception
        $job->failed($exception);

        // Assert - verify the database was deleted
        $this->assertDatabaseMissing('server_databases', [
            'id' => $databaseId,
        ]);
    }

    /**
     * Test failed() method preserves database record data except status and error.
     */
    public function test_failed_method_preserves_database_record_data(): void
    {
        // Arrange
        $server = Server::factory()->create();
        $database = ServerDatabase::factory()->create([
            'server_id' => $server->id,
            'status' => TaskStatus::Active,
            'name' => 'postgresql',
            'version' => '8.0',
            'port' => 3306,
        ]);

        $job = new PostgreSQLInstallerJob($server, $database);
        $exception = new Exception('Installation error');

        // Act
        $job->failed($exception);

        // Assert - verify other fields remain unchanged
        $database->refresh();
        $this->assertEquals('postgresql', $database->name);
        $this->assertEquals('8.0', $database->version);
        $this->assertEquals(3306, $database->port);
        $this->assertEquals($server->id, $database->server_id);
    }

    /**
     * Test handleSuccess creates postgres superuser after successful installation.
     */
    public function test_handle_success_creates_postgres_user(): void
    {
        // Arrange
        $server = Server::factory()->create();
        $database = ServerDatabase::factory()->create([
            'server_id' => $server->id,
            'status' => TaskStatus::Installing,
            'root_password' => 'secret-postgres-password',
        ]);

        $job = new PostgreSQLInstallerJob($server, $database);

        // Act - use reflection to call protected method
        $reflection = new \ReflectionClass($job);
        $method = $reflection->getMethod('handleSuccess');
        $method->setAccessible(true);
        $method->invoke($job, $database);

        // Assert - database status updated to Active
        $database->refresh();
        $this->assertEquals(TaskStatus::Active, $database->status);

        // Assert - postgres user created (PostgreSQL uses 'postgres', not 'root')
        $this->assertDatabaseHas('server_database_users', [
            'server_database_id' => $database->id,
            'is_root' => true,
            'username' => 'postgres',
            'host' => 'localhost',
            'privileges' => 'all',
            'status' => TaskStatus::Active->value,
        ]);

        // Verify postgres user properties
        $postgresUser = ServerDatabaseUser::where('server_database_id', $database->id)
            ->where('is_root', true)
            ->first();

        $this->assertNotNull($postgresUser);
        $this->assertEquals('postgres', $postgresUser->username);
        $this->assertTrue($postgresUser->is_root);
    }
}
