<?php

namespace Tests\Unit\Packages\Services\Database\MySQL;

use App\Models\Server;
use App\Packages\Base\Milestones;
use App\Packages\Contracts\Remover;
use App\Packages\Credentials\RootCredential;
use App\Packages\Credentials\SshCredential;
use App\Packages\Enums\PackageName;
use App\Packages\Services\Database\MySQL\MySqlRemover;
use App\Packages\Services\Database\MySQL\MySqlRemoverMilestones;
use PHPUnit\Framework\MockObject\MockObject;
use Spatie\Ssh\Ssh;
use Tests\TestCase;

class MySqlRemoverTest extends TestCase
{
    private MockObject $server;

    private MySqlRemover $remover;

    protected function setUp(): void
    {
        parent::setUp();

        $this->server = $this->createMock(Server::class);
        $this->server->id = 1;
        $this->server->public_ip = '192.168.1.1';
        $this->server->ssh_port = 22;

        $this->remover = new MySqlRemover($this->server);
    }

    public function test_implements_remover_interface(): void
    {
        $this->assertInstanceOf(Remover::class, $this->remover);
    }

    public function test_service_type_returns_database(): void
    {
        $reflection = new \ReflectionClass($this->remover);
        $method = $reflection->getMethod('serviceType');
        $method->setAccessible(true);

        $this->assertEquals(PackageName::DATABASE, $method->invoke($this->remover));
    }

    public function test_milestones_returns_correct_instance(): void
    {
        $reflection = new \ReflectionClass($this->remover);
        $method = $reflection->getMethod('milestones');
        $method->setAccessible(true);

        $milestones = $method->invoke($this->remover);
        $this->assertInstanceOf(MySqlRemoverMilestones::class, $milestones);
        $this->assertInstanceOf(Milestones::class, $milestones);
    }

    public function test_ssh_credential_returns_root_credential(): void
    {
        $reflection = new \ReflectionClass($this->remover);
        $method = $reflection->getMethod('sshCredential');
        $method->setAccessible(true);

        $credential = $method->invoke($this->remover);
        $this->assertInstanceOf(RootCredential::class, $credential);
        $this->assertInstanceOf(SshCredential::class, $credential);
    }

    public function test_execute_calls_remove_with_commands(): void
    {
        $mockRemover = $this->getMockBuilder(MySqlRemover::class)
            ->setConstructorArgs([$this->server])
            ->onlyMethods(['remove', 'ssh'])
            ->getMock();

        $mockSsh = $this->createMock(Ssh::class);
        $mockRemover->method('ssh')->willReturn($mockSsh);

        $mockRemover->expects($this->once())
            ->method('remove')
            ->with($this->isType('array'));

        $mockRemover->execute();
    }

    public function test_commands_contain_expected_milestones(): void
    {
        $reflection = new \ReflectionClass($this->remover);
        $method = $reflection->getMethod('commands');
        $method->setAccessible(true);

        $commands = $method->invoke($this->remover);

        $closures = array_filter($commands, fn ($command) => $command instanceof \Closure);

        // Should have 7 milestones based on the commands method
        $this->assertCount(7, $closures);
    }

    public function test_commands_contain_expected_removal_commands(): void
    {
        $reflection = new \ReflectionClass($this->remover);
        $method = $reflection->getMethod('commands');
        $method->setAccessible(true);

        $commands = $method->invoke($this->remover);

        $stringCommands = array_filter($commands, 'is_string');

        // Check for key MySQL removal commands
        $commandString = implode(' ', $stringCommands);
        $this->assertStringContainsString('systemctl stop mysql', $commandString);
        $this->assertStringContainsString('systemctl disable mysql', $commandString);
        $this->assertStringContainsString('apt-get remove', $commandString);
        $this->assertStringContainsString('mysql-server', $commandString);
        $this->assertStringContainsString('rm -rf /var/lib/mysql', $commandString);
        $this->assertStringContainsString('userdel mysql', $commandString);
        $this->assertStringContainsString('groupdel mysql', $commandString);
    }

    public function test_commands_include_backup_before_removal(): void
    {
        $reflection = new \ReflectionClass($this->remover);
        $method = $reflection->getMethod('commands');
        $method->setAccessible(true);

        $commands = $method->invoke($this->remover);

        $stringCommands = array_filter($commands, 'is_string');
        $commandString = implode(' ', $stringCommands);

        $this->assertStringContainsString('mysqldump', $commandString);
        $this->assertStringContainsString('all-databases', $commandString);
        $this->assertStringContainsString('/var/backups/mysql-removal-', $commandString);
    }

    public function test_commands_include_firewall_cleanup(): void
    {
        $reflection = new \ReflectionClass($this->remover);
        $method = $reflection->getMethod('commands');
        $method->setAccessible(true);

        $commands = $method->invoke($this->remover);

        $stringCommands = array_filter($commands, 'is_string');
        $commandString = implode(' ', $stringCommands);

        $this->assertStringContainsString('ufw delete allow 3306/tcp', $commandString);
    }

    public function test_commands_include_data_directory_removal(): void
    {
        $reflection = new \ReflectionClass($this->remover);
        $method = $reflection->getMethod('commands');
        $method->setAccessible(true);

        $commands = $method->invoke($this->remover);

        $stringCommands = array_filter($commands, 'is_string');
        $commandString = implode(' ', $stringCommands);

        $this->assertStringContainsString('rm -rf /var/lib/mysql', $commandString);
        $this->assertStringContainsString('rm -rf /var/log/mysql', $commandString);
        $this->assertStringContainsString('rm -rf /etc/mysql', $commandString);
    }

    public function test_commands_include_package_cleanup(): void
    {
        $reflection = new \ReflectionClass($this->remover);
        $method = $reflection->getMethod('commands');
        $method->setAccessible(true);

        $commands = $method->invoke($this->remover);

        $stringCommands = array_filter($commands, 'is_string');
        $commandString = implode(' ', $stringCommands);

        $this->assertStringContainsString('apt-get autoremove', $commandString);
        $this->assertStringContainsString('apt-get clean', $commandString);
        $this->assertStringContainsString('--purge', $commandString);
    }

    public function test_commands_use_non_interactive_mode(): void
    {
        $reflection = new \ReflectionClass($this->remover);
        $method = $reflection->getMethod('commands');
        $method->setAccessible(true);

        $commands = $method->invoke($this->remover);

        $stringCommands = array_filter($commands, 'is_string');
        $commandString = implode(' ', $stringCommands);

        $this->assertStringContainsString('DEBIAN_FRONTEND=noninteractive', $commandString);
    }
}
