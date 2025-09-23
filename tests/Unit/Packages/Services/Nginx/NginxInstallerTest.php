<?php

namespace Tests\Unit\Packages\Services\Nginx;

use App\Models\Server;
use App\Packages\Base\Milestones;
use App\Packages\Contracts\Installer;
use App\Packages\Credentials\RootCredential;
use App\Packages\Credentials\SshCredential;
use App\Packages\Enums\ServiceType;
use App\Packages\Services\Nginx\NginxInstaller;
use App\Packages\Services\Nginx\NginxInstallerMilestones;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\View;
use Mockery;
use Tests\TestCase;

class NginxInstallerTest extends TestCase
{
    use RefreshDatabase;

    private Server $server;

    private NginxInstaller $installer;

    protected function setUp(): void
    {
        parent::setUp();

        $this->server = Server::factory()->create([
            'id' => 1,
            'public_ip' => '192.168.1.1',
            'ssh_port' => 22,
        ]);

        $this->installer = new NginxInstaller($this->server);
    }

    public function test_implements_installer_interface(): void
    {
        $this->assertInstanceOf(Installer::class, $this->installer);
    }

    public function test_service_type_returns_webserver(): void
    {
        $reflection = new \ReflectionClass($this->installer);
        $method = $reflection->getMethod('serviceType');
        $method->setAccessible(true);

        $this->assertEquals(ServiceType::WEBSERVER, $method->invoke($this->installer));
    }

    public function test_milestones_returns_correct_instance(): void
    {
        $reflection = new \ReflectionClass($this->installer);
        $method = $reflection->getMethod('milestones');
        $method->setAccessible(true);

        $milestones = $method->invoke($this->installer);
        $this->assertInstanceOf(NginxInstallerMilestones::class, $milestones);
        $this->assertInstanceOf(Milestones::class, $milestones);
    }

    public function test_ssh_credential_returns_root_credential(): void
    {
        $reflection = new \ReflectionClass($this->installer);
        $method = $reflection->getMethod('sshCredential');
        $method->setAccessible(true);

        $credential = $method->invoke($this->installer);
        $this->assertInstanceOf(RootCredential::class, $credential);
        $this->assertInstanceOf(SshCredential::class, $credential);
    }

    public function test_commands_contain_expected_milestones(): void
    {
        $reflection = new \ReflectionClass($this->installer);
        $method = $reflection->getMethod('commands');
        $method->setAccessible(true);

        $commands = $method->invoke($this->installer, '8.3', 'php8.3-fpm php8.3-cli');

        $closures = array_filter($commands, fn ($command) => $command instanceof \Closure);

        // Should have 11 track milestones + 3 configuration file closures = 14 total
        $this->assertCount(14, $closures);
    }

    public function test_commands_contain_expected_web_server_commands(): void
    {
        $reflection = new \ReflectionClass($this->installer);
        $method = $reflection->getMethod('commands');
        $method->setAccessible(true);

        $commands = $method->invoke($this->installer, '8.3', 'php8.3-fpm php8.3-cli');

        $stringCommands = array_filter($commands, 'is_string');

        // Check for key web server installation commands
        $commandString = implode(' ', $stringCommands);
        $this->assertStringContainsString('apt-get update', $commandString);
        $this->assertStringContainsString('apt-get install', $commandString);
        $this->assertStringContainsString('nginx', $commandString);
        $this->assertStringContainsString('php8.3-fpm', $commandString);
        $this->assertStringContainsString('systemctl enable', $commandString);
    }

    public function test_commands_include_php_packages(): void
    {
        $reflection = new \ReflectionClass($this->installer);
        $method = $reflection->getMethod('commands');
        $method->setAccessible(true);

        $phpPackages = 'php8.3-fpm php8.3-cli php8.3-common php8.3-curl';
        $commands = $method->invoke($this->installer, '8.3', $phpPackages);

        $stringCommands = array_filter($commands, 'is_string');
        $commandString = implode(' ', $stringCommands);

        $this->assertStringContainsString('php8.3-fpm', $commandString);
        $this->assertStringContainsString('php8.3-cli', $commandString);
        $this->assertStringContainsString('php8.3-common', $commandString);
        $this->assertStringContainsString('php8.3-curl', $commandString);
    }

    public function test_commands_include_firewall_configuration(): void
    {
        $reflection = new \ReflectionClass($this->installer);
        $method = $reflection->getMethod('commands');
        $method->setAccessible(true);

        $commands = $method->invoke($this->installer, '8.3', 'php8.3-fpm');

        $stringCommands = array_filter($commands, 'is_string');
        $commandString = implode(' ', $stringCommands);

        $this->assertStringContainsString('ufw allow 80/tcp', $commandString);
        $this->assertStringContainsString('ufw allow 443/tcp', $commandString);
    }

    public function test_commands_include_apache_removal(): void
    {
        $reflection = new \ReflectionClass($this->installer);
        $method = $reflection->getMethod('commands');
        $method->setAccessible(true);

        $commands = $method->invoke($this->installer, '8.3', 'php8.3-fpm');

        $stringCommands = array_filter($commands, 'is_string');
        $commandString = implode(' ', $stringCommands);

        $this->assertStringContainsString('systemctl stop apache2', $commandString);
        $this->assertStringContainsString('systemctl disable apache2', $commandString);
        $this->assertStringContainsString('systemctl mask apache2', $commandString);
        $this->assertStringContainsString('apt-get remove', $commandString);
        $this->assertStringContainsString('apache2', $commandString);
    }

    public function test_commands_include_default_site_creation(): void
    {
        $reflection = new \ReflectionClass($this->installer);
        $method = $reflection->getMethod('commands');
        $method->setAccessible(true);

        $commands = $method->invoke($this->installer, '8.3', 'php8.3-fpm');

        $stringCommands = array_filter($commands, 'is_string');
        $commandString = implode(' ', $stringCommands);

        $this->assertStringContainsString('mkdir -p', $commandString);
        $this->assertStringContainsString('default/public', $commandString);
        $this->assertStringContainsString('chown -R', $commandString);
        $this->assertStringContainsString('chmod', $commandString);
    }

    public function test_commands_include_nginx_configuration(): void
    {
        $reflection = new \ReflectionClass($this->installer);
        $method = $reflection->getMethod('commands');
        $method->setAccessible(true);

        $commands = $method->invoke($this->installer, '8.3', 'php8.3-fpm');

        $stringCommands = array_filter($commands, 'is_string');
        $commandString = implode(' ', $stringCommands);

        $this->assertStringContainsString('nginx -t', $commandString);
        $this->assertStringContainsString('systemctl reload nginx', $commandString);
        $this->assertStringContainsString('sites-available/default', $commandString);
        $this->assertStringContainsString('sites-enabled/default', $commandString);
    }

    public function test_commands_include_view_rendering(): void
    {
        // Mock the view to prevent actual file rendering during tests
        $viewMock = Mockery::mock(\Illuminate\Contracts\View\View::class);
        $viewMock->shouldReceive('render')->andReturn('<?php echo "Default Site"; ?>');

        View::shouldReceive('make')->andReturn($viewMock);

        $reflection = new \ReflectionClass($this->installer);
        $method = $reflection->getMethod('commands');
        $method->setAccessible(true);

        $commands = $method->invoke($this->installer, '8.3', 'php8.3-fpm');

        // Verify we have closures that handle view rendering
        $closures = array_filter($commands, fn ($command) => $command instanceof \Closure);
        $this->assertGreaterThan(10, count($closures));
    }
}
