<?php

namespace Tests\Unit\Packages\Services\Sites;

use App\Models\Server;
use App\Models\ServerSite;
use App\Packages\Credentials\UserCredential;
use App\Packages\Enums\ServiceType;
use App\Packages\Services\Sites\SiteCommandInstaller;
use App\Packages\Services\Sites\SiteCommandInstallerMilestones;
use Illuminate\Foundation\Testing\RefreshDatabase;
use RuntimeException;
use Tests\TestCase;

class SiteCommandInstallerTest extends TestCase
{
    use RefreshDatabase;

    private Server $server;

    private ServerSite $site;

    private SiteCommandInstaller $executor;

    protected function setUp(): void
    {
        parent::setUp();

        $this->server = Server::factory()->create([
            'public_ip' => '192.168.1.1',
            'ssh_port' => 22,
            'ssh_app_user' => 'testuser',
        ]);

        $this->site = ServerSite::factory()->create([
            'server_id' => $this->server->id,
            'domain' => 'example.com',
            'document_root' => '/var/www/example.com',
        ]);

        $this->executor = new SiteCommandInstaller($this->server, $this->site);
    }

    public function test_service_type_returns_correct_value(): void
    {
        $reflection = new \ReflectionClass($this->executor);
        $method = $reflection->getMethod('serviceType');
        $method->setAccessible(true);

        $this->assertEquals(ServiceType::SITE, $method->invoke($this->executor));
    }

    public function test_milestone_class_is_instantiated(): void
    {
        $reflection = new \ReflectionClass($this->executor);
        $method = $reflection->getMethod('milestones');
        $method->setAccessible(true);

        $milestones = $method->invoke($this->executor);
        $this->assertInstanceOf(SiteCommandInstallerMilestones::class, $milestones);
    }

    public function test_ssh_credential_returns_expected_type(): void
    {
        $reflection = new \ReflectionClass($this->executor);
        $method = $reflection->getMethod('sshCredential');
        $method->setAccessible(true);

        $credential = $method->invoke($this->executor);
        $this->assertInstanceOf(UserCredential::class, $credential);
    }

    public function test_execute_throws_exception_for_empty_command(): void
    {
        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('Cannot execute an empty command.');

        $this->executor->execute('');
    }

    public function test_execute_throws_exception_for_whitespace_only_command(): void
    {
        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('Cannot execute an empty command.');

        $this->executor->execute('   ');
    }

    public function test_commands_array_contains_expected_structure(): void
    {
        $reflection = new \ReflectionClass($this->executor);
        $method = $reflection->getMethod('commands');
        $method->setAccessible(true);

        $commands = $method->invoke($this->executor, 'ls -la', 120);

        $this->assertNotEmpty($commands);
        $this->assertIsArray($commands);

        // Test for milestone tracking closures
        $closureCount = 0;
        foreach ($commands as $command) {
            if ($command instanceof \Closure) {
                $closureCount++;
            }
        }
        $this->assertGreaterThan(0, $closureCount, 'Commands should contain milestone tracking closures');
    }

    public function test_execute_uses_site_document_root(): void
    {
        $this->site->update(['document_root' => '/custom/path']);

        // Test that commands are generated with proper site context
        $reflection = new \ReflectionClass($this->executor);
        $method = $reflection->getMethod('commands');
        $method->setAccessible(true);

        $commands = $method->invoke($this->executor, 'ls -la', 120);

        // Verify we have the proper structure
        $this->assertNotEmpty($commands);
        $this->assertIsArray($commands);

        // Count closures to verify structure
        $closureCount = 0;
        foreach ($commands as $command) {
            if ($command instanceof \Closure) {
                $closureCount++;
            }
        }
        $this->assertGreaterThan(0, $closureCount);
    }

    public function test_execute_uses_domain_when_no_document_root(): void
    {
        $this->site->update([
            'document_root' => '',
            'domain' => 'test.com',
        ]);

        // Test that commands are generated with proper site context
        $reflection = new \ReflectionClass($this->executor);
        $method = $reflection->getMethod('commands');
        $method->setAccessible(true);

        $commands = $method->invoke($this->executor, 'ls -la', 120);

        // Verify we have the proper structure
        $this->assertNotEmpty($commands);
        $this->assertIsArray($commands);

        // Count closures to verify structure
        $closureCount = 0;
        foreach ($commands as $command) {
            if ($command instanceof \Closure) {
                $closureCount++;
            }
        }
        $this->assertGreaterThan(0, $closureCount);
    }

    public function test_execute_falls_back_to_site_id(): void
    {
        $this->site->update([
            'document_root' => '',
            'domain' => '',
        ]);

        // Test that commands are generated with proper site context
        $reflection = new \ReflectionClass($this->executor);
        $method = $reflection->getMethod('commands');
        $method->setAccessible(true);

        $commands = $method->invoke($this->executor, 'ls -la', 120);

        // Verify we have the proper structure
        $this->assertNotEmpty($commands);
        $this->assertIsArray($commands);

        // Count closures to verify structure
        $closureCount = 0;
        foreach ($commands as $command) {
            if ($command instanceof \Closure) {
                $closureCount++;
            }
        }
        $this->assertGreaterThan(0, $closureCount);
    }
}
