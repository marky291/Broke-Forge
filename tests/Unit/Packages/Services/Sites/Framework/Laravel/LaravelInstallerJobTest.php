<?php

namespace Tests\Unit\Packages\Services\Sites\Framework\Laravel;

use App\Models\Server;
use App\Models\ServerSite;
use App\Packages\Services\Sites\Framework\Laravel\LaravelInstallerJob;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class LaravelInstallerJobTest extends TestCase
{
    use RefreshDatabase;

    /**
     * Test job has correct timeout property.
     */
    public function test_job_has_correct_timeout_property(): void
    {
        // Arrange & Act
        $server = Server::factory()->create();
        $site = ServerSite::factory()->create(['server_id' => $server->id]);
        $job = new LaravelInstallerJob($server, $site->id);

        // Assert
        $this->assertEquals(600, $job->timeout);
    }

    /**
     * Test job has correct tries property.
     */
    public function test_job_has_correct_tries_property(): void
    {
        // Arrange & Act
        $server = Server::factory()->create();
        $site = ServerSite::factory()->create(['server_id' => $server->id]);
        $job = new LaravelInstallerJob($server, $site->id);

        // Assert
        $this->assertEquals(0, $job->tries);
    }

    /**
     * Test job has correct max exceptions property.
     */
    public function test_job_has_correct_max_exceptions_property(): void
    {
        // Arrange & Act
        $server = Server::factory()->create();
        $site = ServerSite::factory()->create(['server_id' => $server->id]);
        $job = new LaravelInstallerJob($server, $site->id);

        // Assert
        $this->assertEquals(1, $job->maxExceptions);
    }

    /**
     * Test middleware configured with without overlapping.
     */
    public function test_middleware_configured_with_without_overlapping(): void
    {
        // Arrange
        $server = Server::factory()->create();
        $site = ServerSite::factory()->create(['server_id' => $server->id]);
        $job = new LaravelInstallerJob($server, $site->id);

        // Act
        $middleware = $job->middleware();

        // Assert
        $this->assertCount(1, $middleware);
        $this->assertInstanceOf(\Illuminate\Queue\Middleware\WithoutOverlapping::class, $middleware[0]);
    }

    /**
     * Test constructor accepts server and site id.
     */
    public function test_constructor_accepts_server_and_site_id(): void
    {
        // Arrange
        $server = Server::factory()->create();
        $site = ServerSite::factory()->create(['server_id' => $server->id]);

        // Act
        $job = new LaravelInstallerJob($server, $site->id);

        // Assert
        $this->assertInstanceOf(LaravelInstallerJob::class, $job);
        $this->assertEquals($server->id, $job->server->id);
        $this->assertEquals($site->id, $job->siteId);
    }

    /**
     * Test nginx configuration properly escapes double quotes for Laravel sites.
     */
    public function test_nginx_configuration_escapes_double_quotes(): void
    {
        // Arrange
        $server = Server::factory()->create();
        $site = ServerSite::factory()->create([
            'server_id' => $server->id,
            'domain' => 'laravel.test',
            'document_root' => '/home/brokeforge/laravel.test/public',
            'php_version' => '8.2',
            'ssl_enabled' => false,
        ]);
        $job = new LaravelInstallerJob($server, $site->id);

        // Act - use reflection to access protected method
        $reflection = new \ReflectionClass($job);
        $method = $reflection->getMethod('configureNginx');
        $method->setAccessible(true);
        $commands = $method->invoke($job, $site);

        // Assert
        $this->assertIsArray($commands);
        $this->assertCount(5, $commands);

        // Verify the write config command (index 1) has escaped quotes
        $writeConfigCommand = $commands[1];
        $this->assertStringContainsString('\\"SAMEORIGIN\\"', $writeConfigCommand);
        $this->assertStringContainsString('\\"nosniff\\"', $writeConfigCommand);
        $this->assertStringContainsString('\\"1; mode=block\\"', $writeConfigCommand);
    }

    /**
     * Test failed method updates status to failed.
     */
    public function test_failed_method_updates_status_to_failed(): void
    {
        // Arrange
        $server = Server::factory()->create();
        $site = ServerSite::factory()->create([
            'server_id' => $server->id,
            'status' => 'installing',
        ]);
        $job = new LaravelInstallerJob($server, $site->id);
        $exception = new \Exception('Installation failed');

        // Act
        $job->failed($exception);

        // Assert
        $site->refresh();
        $this->assertEquals('failed', $site->status);
        $this->assertEquals('Installation failed', $site->error_log);
    }

    /**
     * Test configure environment file copies .env.example if it exists.
     */
    public function test_configure_environment_file_copies_env_example_when_present(): void
    {
        // Arrange
        $server = Server::factory()->create();
        $site = ServerSite::factory()->create(['server_id' => $server->id]);
        $job = new LaravelInstallerJob($server, $site->id);
        $deploymentPath = '/home/brokeforge/deployments/test.com/12345';

        // Act - use reflection to access protected method
        $reflection = new \ReflectionClass($job);
        $method = $reflection->getMethod('configureEnvironmentFile');
        $method->setAccessible(true);
        $commands = $method->invoke($job, $site, $deploymentPath);

        // Assert
        $this->assertIsArray($commands);
        $this->assertNotEmpty($commands);

        // First command should check for .env.example and copy it
        $this->assertStringContainsString('.env.example', $commands[0]);
        $this->assertStringContainsString('cp', $commands[0]);
        $this->assertStringContainsString('.env', $commands[0]);
    }

    /**
     * Test configure environment file configures database credentials for MySQL.
     */
    public function test_configure_environment_file_configures_mysql_database_credentials(): void
    {
        // Arrange
        $server = Server::factory()->create();
        $database = \App\Models\ServerDatabase::factory()->create([
            'server_id' => $server->id,
            'name' => 'test_database',
            'type' => 'mysql',
            'port' => 3306,
            'root_password' => 'secret_password',
        ]);
        $site = ServerSite::factory()->create([
            'server_id' => $server->id,
            'database_id' => $database->id,
        ]);
        $job = new LaravelInstallerJob($server, $site->id);
        $deploymentPath = '/home/brokeforge/deployments/test.com/12345';

        // Act
        $reflection = new \ReflectionClass($job);
        $method = $reflection->getMethod('configureEnvironmentFile');
        $method->setAccessible(true);
        $commands = $method->invoke($job, $site, $deploymentPath);

        // Assert
        $this->assertIsArray($commands);
        $this->assertGreaterThan(1, count($commands));

        // Join all commands to check for database configuration
        $allCommands = implode(' ', $commands);

        $this->assertStringContainsString('DB_CONNECTION', $allCommands);
        $this->assertStringContainsString('DB_HOST', $allCommands);
        $this->assertStringContainsString('DB_PORT', $allCommands);
        $this->assertStringContainsString('DB_DATABASE', $allCommands);
        $this->assertStringContainsString('DB_USERNAME', $allCommands);
        $this->assertStringContainsString('DB_PASSWORD', $allCommands);

        // Verify specific values
        $this->assertStringContainsString('mysql', $allCommands); // DB_CONNECTION value
        $this->assertStringContainsString('3306', $allCommands); // DB_PORT value
        $this->assertStringContainsString('test_database', $allCommands); // DB_DATABASE value
    }

    /**
     * Test configure environment file configures PostgreSQL database credentials.
     */
    public function test_configure_environment_file_configures_postgresql_database_credentials(): void
    {
        // Arrange
        $server = Server::factory()->create();
        $database = \App\Models\ServerDatabase::factory()->create([
            'server_id' => $server->id,
            'name' => 'postgres_db',
            'type' => 'postgresql',
            'port' => 5432,
            'root_password' => 'pg_password',
        ]);
        $site = ServerSite::factory()->create([
            'server_id' => $server->id,
            'database_id' => $database->id,
        ]);
        $job = new LaravelInstallerJob($server, $site->id);
        $deploymentPath = '/home/brokeforge/deployments/test.com/12345';

        // Act
        $reflection = new \ReflectionClass($job);
        $method = $reflection->getMethod('configureEnvironmentFile');
        $method->setAccessible(true);
        $commands = $method->invoke($job, $site, $deploymentPath);

        // Assert
        $allCommands = implode(' ', $commands);

        // Verify PostgreSQL is mapped to 'pgsql' for Laravel
        $this->assertStringContainsString('pgsql', $allCommands);
        $this->assertStringContainsString('5432', $allCommands);
        $this->assertStringContainsString('postgres_db', $allCommands);
    }

    /**
     * Test configure environment file handles sites without database.
     */
    public function test_configure_environment_file_handles_sites_without_database(): void
    {
        // Arrange
        $server = Server::factory()->create();
        $site = ServerSite::factory()->create([
            'server_id' => $server->id,
            'database_id' => null,
        ]);
        $job = new LaravelInstallerJob($server, $site->id);
        $deploymentPath = '/home/brokeforge/deployments/test.com/12345';

        // Act
        $reflection = new \ReflectionClass($job);
        $method = $reflection->getMethod('configureEnvironmentFile');
        $method->setAccessible(true);
        $commands = $method->invoke($job, $site, $deploymentPath);

        // Assert
        $this->assertIsArray($commands);
        $this->assertCount(1, $commands); // Only the .env creation command

        // Should still create .env file
        $this->assertStringContainsString('.env', $commands[0]);
    }

    /**
     * Test configure environment file escapes special characters in password.
     */
    public function test_configure_environment_file_escapes_special_characters_in_password(): void
    {
        // Arrange
        $server = Server::factory()->create();
        $database = \App\Models\ServerDatabase::factory()->create([
            'server_id' => $server->id,
            'name' => 'test_db',
            'type' => 'mysql',
            'port' => 3306,
            'root_password' => 'pass/word&special\\chars',
        ]);
        $site = ServerSite::factory()->create([
            'server_id' => $server->id,
            'database_id' => $database->id,
        ]);
        $job = new LaravelInstallerJob($server, $site->id);
        $deploymentPath = '/home/brokeforge/deployments/test.com/12345';

        // Act
        $reflection = new \ReflectionClass($job);
        $method = $reflection->getMethod('configureEnvironmentFile');
        $method->setAccessible(true);
        $commands = $method->invoke($job, $site, $deploymentPath);

        // Assert
        $allCommands = implode(' ', $commands);

        // Password should be properly escaped for sed
        // Original password in PHP: 'pass/word&special\\chars' (which is pass/word&special\chars as a string)
        // After sed escaping with our str_replace: pass\/word\&special\\chars
        // In the command output string, this appears with escaped backslashes: pass\\/word\\&special\\chars
        $this->assertStringContainsString('DB_PASSWORD=pass\\\\/word\\\\&special\\\\chars', $allCommands);
    }

    /**
     * Test configure environment file quotes passwords with spaces.
     */
    public function test_configure_environment_file_quotes_passwords_with_spaces(): void
    {
        // Arrange
        $server = Server::factory()->create();
        $database = \App\Models\ServerDatabase::factory()->create([
            'server_id' => $server->id,
            'name' => 'test_db',
            'type' => 'mysql',
            'port' => 3306,
            'root_password' => 'my secret password',
        ]);
        $site = ServerSite::factory()->create([
            'server_id' => $server->id,
            'database_id' => $database->id,
        ]);
        $job = new LaravelInstallerJob($server, $site->id);
        $deploymentPath = '/home/brokeforge/deployments/test.com/12345';

        // Act
        $reflection = new \ReflectionClass($job);
        $method = $reflection->getMethod('configureEnvironmentFile');
        $method->setAccessible(true);
        $commands = $method->invoke($job, $site, $deploymentPath);

        // Assert
        $allCommands = implode(' ', $commands);

        // Password with spaces should be quoted
        $this->assertStringContainsString('DB_PASSWORD="my secret password"', $allCommands);
    }
}
