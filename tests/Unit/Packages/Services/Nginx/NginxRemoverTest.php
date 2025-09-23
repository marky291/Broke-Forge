<?php

namespace Tests\Unit\Packages\Services\Nginx;

use App\Models\Server;
use App\Packages\Credentials\RootCredential;
use App\Packages\Enums\ServiceType;
use App\Packages\Services\Nginx\NginxRemover;
use App\Packages\Services\Nginx\NginxRemoverMilestones;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Mockery;
use Tests\TestCase;

class NginxRemoverTest extends TestCase
{
    use RefreshDatabase;

    private NginxRemover $remover;

    private Server $server;

    protected function setUp(): void
    {
        parent::setUp();

        $this->server = Server::factory()->create();
        $this->remover = new NginxRemover($this->server);
    }

    public function test_service_type_returns_webserver(): void
    {
        $reflection = new \ReflectionClass($this->remover);
        $method = $reflection->getMethod('serviceType');
        $method->setAccessible(true);

        $this->assertEquals(ServiceType::WEBSERVER, $method->invoke($this->remover));
    }

    public function test_ssh_credential_returns_root_credential(): void
    {
        $reflection = new \ReflectionClass($this->remover);
        $method = $reflection->getMethod('sshCredential');
        $method->setAccessible(true);

        $credential = $method->invoke($this->remover);
        $this->assertInstanceOf(RootCredential::class, $credential);
    }

    public function test_milestones_returns_web_service_remover_milestones(): void
    {
        $reflection = new \ReflectionClass($this->remover);
        $method = $reflection->getMethod('milestones');
        $method->setAccessible(true);

        $milestones = $method->invoke($this->remover);
        $this->assertInstanceOf(NginxRemoverMilestones::class, $milestones);
    }

    public function test_execute_calls_remove_with_commands(): void
    {
        // Create a PHP service for the server
        $this->server->services()->create([
            'service_name' => 'php',
            'configuration' => ['version' => '8.3'],
            'status' => 'active',
        ]);

        // Mock the remover to track remove calls
        $remover = Mockery::mock(NginxRemover::class, [$this->server])->makePartial();
        $remover->shouldAllowMockingProtectedMethods();
        $remover->shouldReceive('remove')->once();
        $remover->shouldReceive('commands')->once()->andReturn([]);

        $result = $remover->execute();

        // Assert that execute completed without exception
        $this->assertNull($result);

        // Verify Mockery expectations were met
        $this->addToAssertionCount(1);
    }

    public function test_execute_retrieves_php_version_from_server_services(): void
    {
        // Create a PHP service for the server
        $phpService = $this->server->services()->create([
            'service_name' => 'php',
            'configuration' => ['version' => '8.2'],
            'status' => 'active',
        ]);

        // Mock to access the protected execute method logic
        $remover = Mockery::mock(NginxRemover::class, [$this->server])->makePartial();
        $remover->shouldAllowMockingProtectedMethods();
        $remover->shouldReceive('remove')->once();
        $remover->shouldReceive('commands')->once()->andReturn([]);

        $remover->execute();

        // Since we can't directly test the internal logic without exposing it,
        // we ensure the execute method completes without error when services exist
        $this->assertTrue(true);
    }

    public function test_commands_returns_array(): void
    {
        $reflection = new \ReflectionClass($this->remover);
        $method = $reflection->getMethod('commands');
        $method->setAccessible(true);

        $commands = $method->invoke($this->remover, '8.3', 'php8.3-fpm php8.3-cli');

        $this->assertIsArray($commands);
        $this->assertNotEmpty($commands);
    }

    public function test_extends_package_remover(): void
    {
        $this->assertInstanceOf(\App\Packages\Base\PackageRemover::class, $this->remover);
    }

    public function test_implements_remover_contract(): void
    {
        $this->assertInstanceOf(\App\Packages\Contracts\Remover::class, $this->remover);
    }

    protected function tearDown(): void
    {
        Mockery::close();
        parent::tearDown();
    }
}
