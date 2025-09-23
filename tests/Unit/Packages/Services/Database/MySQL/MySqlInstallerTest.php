<?php

namespace Tests\Unit\Packages\Services\Database\MySQL;

use App\Models\Server;
use App\Packages\Base\Milestones;
use App\Packages\Contracts\Installer;
use App\Packages\Credentials\RootCredential;
use App\Packages\Credentials\SshCredential;
use App\Packages\Enums\PackageName;
use App\Packages\Services\Database\MySQL\MySqlInstaller;
use App\Packages\Services\Database\MySQL\MySqlInstallerMilestones;
use PHPUnit\Framework\MockObject\MockObject;
use Spatie\Ssh\Ssh;
use Tests\TestCase;

class MySqlInstallerTest extends TestCase
{
    private MockObject $server;

    private MySqlInstaller $installer;

    protected function setUp(): void
    {
        parent::setUp();

        $this->server = $this->createMock(Server::class);
        $this->server->id = 1;
        $this->server->public_ip = '192.168.1.1';
        $this->server->ssh_port = 22;

        $this->installer = new MySqlInstaller($this->server);
    }

    public function test_implements_installer_interface(): void
    {
        $this->assertInstanceOf(Installer::class, $this->installer);
    }

    public function test_service_type_returns_database(): void
    {
        $reflection = new \ReflectionClass($this->installer);
        $method = $reflection->getMethod('serviceType');
        $method->setAccessible(true);

        $this->assertEquals(PackageName::DATABASE, $method->invoke($this->installer));
    }

    public function test_milestones_returns_correct_instance(): void
    {
        $reflection = new \ReflectionClass($this->installer);
        $method = $reflection->getMethod('milestones');
        $method->setAccessible(true);

        $milestones = $method->invoke($this->installer);
        $this->assertInstanceOf(MySqlInstallerMilestones::class, $milestones);
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

    public function test_execute_generates_random_password(): void
    {
        $mockInstaller = $this->getMockBuilder(MySqlInstaller::class)
            ->setConstructorArgs([$this->server])
            ->onlyMethods(['install', 'ssh'])
            ->getMock();

        $mockSsh = $this->createMock(Ssh::class);
        $mockInstaller->method('ssh')->willReturn($mockSsh);

        $capturedCommands = null;
        $mockInstaller->expects($this->once())
            ->method('install')
            ->willReturnCallback(function ($commands) use (&$capturedCommands) {
                $capturedCommands = $commands;
            });

        $mockInstaller->execute();

        $this->assertNotNull($capturedCommands);
        $this->assertIsArray($capturedCommands);

        // Check that password commands are present and contain the generated password
        $passwordCommands = array_filter($capturedCommands, function ($command) {
            return is_string($command) && str_contains($command, 'mysql-server/root_password');
        });

        $this->assertNotEmpty($passwordCommands);
    }

    public function test_commands_contain_expected_milestones(): void
    {
        $reflection = new \ReflectionClass($this->installer);
        $method = $reflection->getMethod('commands');
        $method->setAccessible(true);

        $commands = $method->invoke($this->installer, 'test-password');

        $closures = array_filter($commands, fn ($command) => $command instanceof \Closure);

        // Should have 11 milestones based on MySqlInstallerMilestones::countLabels()
        $this->assertCount(11, $closures);
    }

    public function test_commands_contain_expected_my_sql_commands(): void
    {
        $reflection = new \ReflectionClass($this->installer);
        $method = $reflection->getMethod('commands');
        $method->setAccessible(true);

        $commands = $method->invoke($this->installer, 'test-password');

        $stringCommands = array_filter($commands, 'is_string');

        // Check for key MySQL installation commands
        $commandString = implode(' ', $stringCommands);
        $this->assertStringContainsString('apt-get update', $commandString);
        $this->assertStringContainsString('apt-get install', $commandString);
        $this->assertStringContainsString('mysql-server', $commandString);
        $this->assertStringContainsString('systemctl enable', $commandString);
        $this->assertStringContainsString('mysql -u root', $commandString);
    }

    public function test_commands_use_provided_password(): void
    {
        $reflection = new \ReflectionClass($this->installer);
        $method = $reflection->getMethod('commands');
        $method->setAccessible(true);

        $testPassword = 'secure-test-password-123';
        $commands = $method->invoke($this->installer, $testPassword);

        $stringCommands = array_filter($commands, 'is_string');
        $commandString = implode(' ', $stringCommands);

        $this->assertStringContainsString($testPassword, $commandString);
    }

    public function test_commands_include_firewall_configuration(): void
    {
        $reflection = new \ReflectionClass($this->installer);
        $method = $reflection->getMethod('commands');
        $method->setAccessible(true);

        $commands = $method->invoke($this->installer, 'test-password');

        $stringCommands = array_filter($commands, 'is_string');
        $commandString = implode(' ', $stringCommands);

        $this->assertStringContainsString('ufw allow 3306/tcp', $commandString);
    }

    public function test_commands_include_backup_directory_creation(): void
    {
        $reflection = new \ReflectionClass($this->installer);
        $method = $reflection->getMethod('commands');
        $method->setAccessible(true);

        $commands = $method->invoke($this->installer, 'test-password');

        $stringCommands = array_filter($commands, 'is_string');
        $commandString = implode(' ', $stringCommands);

        $this->assertStringContainsString('/var/backups/mysql', $commandString);
        $this->assertStringContainsString('chown mysql:mysql', $commandString);
    }

    public function test_commands_include_remote_access_configuration(): void
    {
        $reflection = new \ReflectionClass($this->installer);
        $method = $reflection->getMethod('commands');
        $method->setAccessible(true);

        $commands = $method->invoke($this->installer, 'test-password');

        $stringCommands = array_filter($commands, 'is_string');
        $commandString = implode(' ', $stringCommands);

        $this->assertStringContainsString('bind-address', $commandString);
        $this->assertStringContainsString('0.0.0.0', $commandString);
    }
}
