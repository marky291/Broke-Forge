<?php

namespace Tests\Unit\Packages\Services\Sites;

use App\Models\Server;
use App\Models\ServerSite;
use App\Packages\Credentials\UserCredential;
euse App\Packages\Enums\PackageName;
use App\Packages\Services\Sites\SiteInstaller;
use App\Packages\Services\Sites\SiteInstallerMilestones;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Mockery;
use Tests\TestCase;

class SiteInstallerTest extends TestCase
{
    use RefreshDatabase;

    private SiteInstaller $installer;

    private Server $server;

    protected function setUp(): void
    {
        parent::setUp();

        $this->server = Server::factory()->create();
        $this->installer = new SiteInstaller($this->server);
    }

    public function test_service_type_returns_site(): void
    {
        $reflection = new \ReflectionClass($this->installer);
        $method = $reflection->getMethod('serviceType');
        $method->setAccessible(true);

        $this->assertEquals(PackageName::SITE, $method->invoke($this->installer));
    }

    public function test_ssh_credential_returns_user_credential(): void
    {
        $reflection = new \ReflectionClass($this->installer);
        $method = $reflection->getMethod('sshCredential');
        $method->setAccessible(true);

        $credential = $method->invoke($this->installer);
        $this->assertInstanceOf(UserCredential::class, $credential);
    }

    public function test_milestones_returns_site_installer_milestones(): void
    {
        $reflection = new \ReflectionClass($this->installer);
        $method = $reflection->getMethod('milestones');
        $method->setAccessible(true);

        $milestones = $method->invoke($this->installer);
        $this->assertInstanceOf(SiteInstallerMilestones::class, $milestones);
    }

    public function test_execute_creates_server_site_record(): void
    {
        $config = [
            'domain' => 'example.com',
            'php_version' => '8.3',
            'ssl' => true,
        ];

        // Mock the install method to prevent actual SSH execution
        $installerMock = $this->getMockBuilder(SiteInstaller::class)
            ->setConstructorArgs([$this->server])
            ->onlyMethods(['install'])
            ->getMock();

        $installerMock->expects($this->once())->method('install');

        $site = $installerMock->execute($config);

        $this->assertInstanceOf(ServerSite::class, $site);
        $this->assertEquals('example.com', $site->domain);
        $this->assertEquals('8.3', $site->php_version);
        $this->assertTrue($site->ssl_enabled);
    }

    public function test_execute_uses_default_document_root(): void
    {
        $config = ['domain' => 'example.com'];

        // Mock the install method to prevent actual SSH execution
        $installerMock = $this->getMockBuilder(SiteInstaller::class)
            ->setConstructorArgs([$this->server])
            ->onlyMethods(['install'])
            ->getMock();

        $installerMock->expects($this->once())->method('install');

        $site = $installerMock->execute($config);

        $this->assertStringContainsString('/home/', $site->document_root);
        $this->assertStringContainsString('example.com/public', $site->document_root);
    }

    public function test_execute_allows_custom_document_root(): void
    {
        $config = [
            'domain' => 'example.com',
            'document_root' => '/var/www/custom',
        ];

        // Mock the install method to prevent actual SSH execution
        $installerMock = $this->getMockBuilder(SiteInstaller::class)
            ->setConstructorArgs([$this->server])
            ->onlyMethods(['install'])
            ->getMock();

        $installerMock->expects($this->once())->method('install');

        $site = $installerMock->execute($config);

        $this->assertEquals('/var/www/custom', $site->document_root);
    }

    public function test_execute_requires_domain(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('Domain is required for site installation.');

        $this->installer->execute([]);
    }

    public function test_execute_detects_php_version_from_server(): void
    {
        // Create a PHP service for the server
        $this->server->packages()->create([
            'service_name' => 'php',
            'configuration' => ['version' => '8.2'],
            'status' => 'active',
        ]);

        $config = ['domain' => 'example.com'];

        // Mock the install method to prevent actual SSH execution
        $installerMock = $this->getMockBuilder(SiteInstaller::class)
            ->setConstructorArgs([$this->server])
            ->onlyMethods(['install'])
            ->getMock();

        $installerMock->expects($this->once())->method('install');

        $site = $installerMock->execute($config);

        $this->assertEquals('8.2', $site->php_version);
    }

    public function test_execute_uses_default_php_version_when_no_service(): void
    {
        $config = ['domain' => 'example.com'];

        // Mock the install method to prevent actual SSH execution
        $installerMock = $this->getMockBuilder(SiteInstaller::class)
            ->setConstructorArgs([$this->server])
            ->onlyMethods(['install'])
            ->getMock();

        $installerMock->expects($this->once())->method('install');

        $site = $installerMock->execute($config);

        $this->assertEquals('8.3', $site->php_version);
    }

    public function test_execute_generates_nginx_configuration(): void
    {
        $config = [
            'domain' => 'test.com',
            'document_root' => '/var/www/test',
            'php_version' => '8.3',
        ];

        // Mock the install method to prevent actual SSH execution
        $installerMock = $this->getMockBuilder(SiteInstaller::class)
            ->setConstructorArgs([$this->server])
            ->onlyMethods(['install'])
            ->getMock();

        $installerMock->expects($this->once())
            ->method('install')
            ->with($this->callback(function ($commands) {
                $commandString = implode(' ', array_filter($commands, 'is_string'));
                return str_contains($commandString, 'mkdir -p /var/www/test') &&
                       str_contains($commandString, '/etc/nginx/sites-available/test.com');
            }));

        $installerMock->execute($config);
    }

    protected function tearDown(): void
    {
        Mockery::close();
        parent::tearDown();
    }
}
