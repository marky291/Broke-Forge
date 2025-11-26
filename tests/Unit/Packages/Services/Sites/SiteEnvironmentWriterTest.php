<?php

namespace Tests\Unit\Packages\Services\Sites;

use App\Models\AvailableFramework;
use App\Models\Server;
use App\Models\ServerSite;
use App\Packages\Services\Sites\SiteEnvironmentWriter;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Mockery;
use Spatie\Ssh\Ssh;
use Symfony\Component\Process\Process;
use Tests\Concerns\MocksSshConnections;
use Tests\TestCase;

class SiteEnvironmentWriterTest extends TestCase
{
    use MocksSshConnections, RefreshDatabase;

    /**
     * Test getEnvFilePath returns correct path with shared directory.
     */
    public function test_get_env_file_path_returns_path_with_shared_directory(): void
    {
        $framework = AvailableFramework::factory()->create([
            'env' => ['file_path' => '.env', 'supports' => true],
        ]);

        $server = Server::factory()->create();
        $site = ServerSite::factory()->create([
            'server_id' => $server->id,
            'available_framework_id' => $framework->id,
            'domain' => 'example.com',
        ]);

        $writer = new SiteEnvironmentWriter($site);

        // Use reflection to access the protected method
        $reflectionMethod = new \ReflectionMethod($writer, 'getEnvFilePath');
        $reflectionMethod->setAccessible(true);
        $path = $reflectionMethod->invoke($writer);

        $this->assertEquals('/home/brokeforge/deployments/example.com/shared/.env', $path);
    }

    /**
     * Test getEnvFilePath returns null when framework does not support env.
     */
    public function test_get_env_file_path_returns_null_when_framework_does_not_support_env(): void
    {
        // Get or create static-html framework
        $framework = AvailableFramework::firstOrCreate(
            ['slug' => 'static-html'],
            [
                'name' => 'Static HTML',
                'env' => ['file_path' => null, 'supports' => false],
                'requirements' => ['database' => false, 'redis' => false, 'nodejs' => false, 'composer' => false],
                'description' => 'Static HTML/CSS/JS website',
            ]
        );

        $server = Server::factory()->create();
        $site = ServerSite::factory()->create([
            'server_id' => $server->id,
            'available_framework_id' => $framework->id,
            'domain' => 'example.com',
        ]);

        $writer = new SiteEnvironmentWriter($site);

        $reflectionMethod = new \ReflectionMethod($writer, 'getEnvFilePath');
        $reflectionMethod->setAccessible(true);
        $path = $reflectionMethod->invoke($writer);

        $this->assertNull($path);
    }

    /**
     * Test execute throws exception when framework does not support env.
     */
    public function test_execute_throws_exception_when_framework_does_not_support_env(): void
    {
        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage('Framework does not support environment file editing');

        // Get or create static-html framework
        $framework = AvailableFramework::firstOrCreate(
            ['slug' => 'static-html'],
            [
                'name' => 'Static HTML',
                'env' => ['file_path' => null, 'supports' => false],
                'requirements' => ['database' => false, 'redis' => false, 'nodejs' => false, 'composer' => false],
                'description' => 'Static HTML/CSS/JS website',
            ]
        );

        $server = Server::factory()->create();
        $site = ServerSite::factory()->create([
            'server_id' => $server->id,
            'available_framework_id' => $framework->id,
        ]);

        $writer = new SiteEnvironmentWriter($site);
        $writer->execute('APP_NAME=Test');
    }

    /**
     * Test execute writes env file successfully via SSH using base64 encoding.
     */
    public function test_execute_writes_env_file_via_ssh_with_base64_encoding(): void
    {
        $framework = AvailableFramework::factory()->create([
            'env' => ['file_path' => '.env', 'supports' => true],
        ]);

        $server = Server::factory()->create();
        $site = ServerSite::factory()->create([
            'server_id' => $server->id,
            'available_framework_id' => $framework->id,
            'domain' => 'example.com',
        ]);

        $content = "APP_NAME=MyApp\nAPP_ENV=production\nAPP_DEBUG=false";
        $encodedContent = base64_encode($content);
        $envPath = '/home/brokeforge/deployments/example.com/shared/.env';

        $expectedCommand = sprintf(
            'echo %s | base64 -d > %s',
            escapeshellarg($encodedContent),
            escapeshellarg($envPath)
        );

        $this->mockSshConnection($server, [
            $expectedCommand => [
                'success' => true,
                'output' => '',
            ],
        ]);

        $writer = new SiteEnvironmentWriter($site);
        $writer->execute($content);

        // If we get here without exception, the test passes
        $this->assertTrue(true);
    }

    /**
     * Test execute throws exception when SSH write fails.
     */
    public function test_execute_throws_exception_when_ssh_write_fails(): void
    {
        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage('Failed to write environment file:');

        $framework = AvailableFramework::factory()->create([
            'env' => ['file_path' => '.env', 'supports' => true],
        ]);

        $server = Server::factory()->create();
        $site = ServerSite::factory()->create([
            'server_id' => $server->id,
            'available_framework_id' => $framework->id,
            'domain' => 'example.com',
        ]);

        $content = 'APP_NAME=MyApp';
        $encodedContent = base64_encode($content);
        $envPath = '/home/brokeforge/deployments/example.com/shared/.env';

        $expectedCommand = sprintf(
            'echo %s | base64 -d > %s',
            escapeshellarg($encodedContent),
            escapeshellarg($envPath)
        );

        // Mock SSH with the credential service approach
        $mockSsh = Mockery::mock(Ssh::class);
        $mockProcess = Mockery::mock(Process::class);

        $mockSsh->shouldReceive('disableStrictHostKeyChecking')->andReturnSelf();
        $mockSsh->shouldReceive('setTimeout')->andReturnSelf();
        $mockSsh->shouldReceive('execute')
            ->with($expectedCommand)
            ->andReturn($mockProcess);

        $mockProcess->shouldReceive('isSuccessful')->andReturn(false);
        $mockProcess->shouldReceive('getErrorOutput')->andReturn('Permission denied');

        $mockCredentialSsh = Mockery::mock(\App\Packages\Credential\Ssh::class);
        $mockCredentialSsh->shouldReceive('connect')
            ->with(Mockery::type(Server::class), Mockery::any())
            ->andReturn($mockSsh);

        app()->instance(\App\Packages\Credential\Ssh::class, $mockCredentialSsh);

        $writer = new SiteEnvironmentWriter($site);
        $writer->execute($content);
    }

    /**
     * Test getEnvFilePath uses correct framework env file path for WordPress.
     */
    public function test_get_env_file_path_uses_framework_env_file_path(): void
    {
        // Get or create WordPress framework
        $framework = AvailableFramework::firstOrCreate(
            ['slug' => 'wordpress'],
            [
                'name' => 'WordPress',
                'env' => ['file_path' => 'wp-config.php', 'supports' => true],
                'requirements' => ['database' => true, 'redis' => false, 'nodejs' => false, 'composer' => false],
                'description' => 'WordPress CMS with PHP and MySQL',
            ]
        );

        $server = Server::factory()->create();
        $site = ServerSite::factory()->create([
            'server_id' => $server->id,
            'available_framework_id' => $framework->id,
            'domain' => 'wordpress-site.com',
        ]);

        $writer = new SiteEnvironmentWriter($site);

        $reflectionMethod = new \ReflectionMethod($writer, 'getEnvFilePath');
        $reflectionMethod->setAccessible(true);
        $path = $reflectionMethod->invoke($writer);

        $this->assertEquals('/home/brokeforge/deployments/wordpress-site.com/shared/wp-config.php', $path);
    }

    /**
     * Test execute handles special characters via base64 encoding.
     */
    public function test_execute_handles_special_characters_via_base64(): void
    {
        $framework = AvailableFramework::factory()->create([
            'env' => ['file_path' => '.env', 'supports' => true],
        ]);

        $server = Server::factory()->create();
        $site = ServerSite::factory()->create([
            'server_id' => $server->id,
            'available_framework_id' => $framework->id,
            'domain' => 'example.com',
        ]);

        // Content with special characters that could break shell commands
        $content = "APP_NAME=\"My App\"\nDB_PASSWORD='s3cr3t!@#\$%^&*()'";
        $encodedContent = base64_encode($content);
        $envPath = '/home/brokeforge/deployments/example.com/shared/.env';

        $expectedCommand = sprintf(
            'echo %s | base64 -d > %s',
            escapeshellarg($encodedContent),
            escapeshellarg($envPath)
        );

        $this->mockSshConnection($server, [
            $expectedCommand => [
                'success' => true,
                'output' => '',
            ],
        ]);

        $writer = new SiteEnvironmentWriter($site);
        $writer->execute($content);

        // If we get here without exception, the test passes
        $this->assertTrue(true);
    }
}
