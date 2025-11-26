<?php

namespace Tests\Unit\Packages\Services\Sites;

use App\Models\AvailableFramework;
use App\Models\Server;
use App\Models\ServerSite;
use App\Packages\Services\Sites\SiteEnvironmentReader;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Concerns\MocksSshConnections;
use Tests\TestCase;

class SiteEnvironmentReaderTest extends TestCase
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

        $reader = new SiteEnvironmentReader($site);

        // Use reflection to access the protected method
        $reflectionMethod = new \ReflectionMethod($reader, 'getEnvFilePath');
        $reflectionMethod->setAccessible(true);
        $path = $reflectionMethod->invoke($reader);

        $this->assertEquals('/home/brokeforge/deployments/example.com/shared/.env', $path);
    }

    /**
     * Test getEnvFilePath returns null when framework does not support env.
     */
    public function test_get_env_file_path_returns_null_when_framework_does_not_support_env(): void
    {
        $framework = AvailableFramework::factory()->staticHtml()->create();

        $server = Server::factory()->create();
        $site = ServerSite::factory()->create([
            'server_id' => $server->id,
            'available_framework_id' => $framework->id,
            'domain' => 'example.com',
        ]);

        $reader = new SiteEnvironmentReader($site);

        $reflectionMethod = new \ReflectionMethod($reader, 'getEnvFilePath');
        $reflectionMethod->setAccessible(true);
        $path = $reflectionMethod->invoke($reader);

        $this->assertNull($path);
    }

    /**
     * Test execute throws exception when framework does not support env.
     */
    public function test_execute_throws_exception_when_framework_does_not_support_env(): void
    {
        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage('Framework does not support environment file editing');

        $framework = AvailableFramework::factory()->staticHtml()->create();

        $server = Server::factory()->create();
        $site = ServerSite::factory()->create([
            'server_id' => $server->id,
            'available_framework_id' => $framework->id,
        ]);

        $reader = new SiteEnvironmentReader($site);
        $reader->execute();
    }

    /**
     * Test execute reads env file successfully via SSH.
     */
    public function test_execute_reads_env_file_via_ssh(): void
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

        $expectedEnvContent = "APP_NAME=MyApp\nAPP_ENV=production\nAPP_DEBUG=false";
        $envPath = '/home/brokeforge/deployments/example.com/shared/.env';

        $this->mockSshConnection($server, [
            sprintf('cat %s 2>/dev/null || echo ""', escapeshellarg($envPath)) => [
                'success' => true,
                'output' => $expectedEnvContent,
            ],
        ]);

        $reader = new SiteEnvironmentReader($site);
        $content = $reader->execute();

        $this->assertEquals($expectedEnvContent, $content);
    }

    /**
     * Test execute returns empty string when file does not exist.
     */
    public function test_execute_returns_empty_string_when_file_does_not_exist(): void
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

        $envPath = '/home/brokeforge/deployments/example.com/shared/.env';

        $this->mockSshConnection($server, [
            sprintf('cat %s 2>/dev/null || echo ""', escapeshellarg($envPath)) => [
                'success' => true,
                'output' => '',
            ],
        ]);

        $reader = new SiteEnvironmentReader($site);
        $content = $reader->execute();

        $this->assertEquals('', $content);
    }

    /**
     * Test getEnvFilePath uses correct framework env file path for WordPress.
     */
    public function test_get_env_file_path_uses_framework_env_file_path(): void
    {
        $framework = AvailableFramework::factory()->wordpress()->create();

        $server = Server::factory()->create();
        $site = ServerSite::factory()->create([
            'server_id' => $server->id,
            'available_framework_id' => $framework->id,
            'domain' => 'wordpress-site.com',
        ]);

        $reader = new SiteEnvironmentReader($site);

        $reflectionMethod = new \ReflectionMethod($reader, 'getEnvFilePath');
        $reflectionMethod->setAccessible(true);
        $path = $reflectionMethod->invoke($reader);

        $this->assertEquals('/home/brokeforge/deployments/wordpress-site.com/shared/wp-config.php', $path);
    }
}
